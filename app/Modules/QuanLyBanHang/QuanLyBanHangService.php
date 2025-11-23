<?php

namespace App\Modules\QuanLyBanHang;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuanLyBanHangService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = DonHang::query()->with('images');

            // ✅ Sắp xếp DH00284 → DH00283 → … theo phần số sau “DH”
            // (để an toàn, tie-break thêm theo id desc)
            $query->orderByRaw('CAST(SUBSTRING(don_hangs.ma_don_hang, 3) AS UNSIGNED) DESC')
                  ->orderByDesc('don_hangs.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['don_hangs.*'] // Columns cần select
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = DonHang::with(
            'khachHang',
            'chiTietDonHangs.sanPham.danhMuc', // 🔹 load luôn danh mục để biết group_code
            'chiTietDonHangs.donViTinh'
        )->find($id);


        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    /**
     * Chuẩn hoá thanh toán theo loại thanh toán (an toàn dữ liệu)
     * - loai_thanh_toan: 0=Chưa TT, 1=TT một phần, 2=TT toàn bộ
     * - chỉ thiết lập so_tien_da_thanh_toan & trang_thai_thanh_toan
     * - KHÔNG ghi so_tien_con_lai vào DB (tránh yêu cầu thêm cột)
     */
    private function normalizePayments(array &$data, int $tongTienCanThanhToan): void
    {
        $loai = (int)($data['loai_thanh_toan'] ?? 0);
        $daTT = (int)($data['so_tien_da_thanh_toan'] ?? 0);

        if ($loai === 0) {
            // Chưa thanh toán
            $daTT = 0;
        } elseif ($loai === 2) {
            // Thanh toán toàn bộ
            $daTT = $tongTienCanThanhToan;
        } else {
            // Thanh toán một phần: kẹp 0..tổng
            if ($daTT < 0) $daTT = 0;
            if ($daTT > $tongTienCanThanhToan) $daTT = $tongTienCanThanhToan;
        }

        $data['so_tien_da_thanh_toan'] = $daTT;

        // Dẫn xuất "còn lại" để quyết định trạng thái (không lưu DB)
        $conLai = max(0, $tongTienCanThanhToan - $daTT);

        /**
         * 0 = chưa thanh toán
         * 1 = thanh toán một phần
         * 2 = đã thanh toán đủ
         */
        if ($daTT <= 0) {
            $data['trang_thai_thanh_toan'] = 0;
        } elseif ($conLai > 0) {
            $data['trang_thai_thanh_toan'] = 1;
        } else {
            $data['trang_thai_thanh_toan'] = 2;
        }

        unset($data['so_tien_con_lai']);
    }

    /**
     * Chuẩn hoá thông tin người nhận (Tên/SĐT/Ngày giờ nhận)
     */
    private function normalizeRecipientFields(array &$data): void
    {
        if (array_key_exists('nguoi_nhan_ten', $data) && $data['nguoi_nhan_ten'] !== null) {
            $data['nguoi_nhan_ten'] = trim((string)$data['nguoi_nhan_ten']);
        }

        if (array_key_exists('nguoi_nhan_sdt', $data) && $data['nguoi_nhan_sdt'] !== null) {
            $data['nguoi_nhan_sdt'] = trim((string)$data['nguoi_nhan_sdt']);
        }

        if (array_key_exists('nguoi_nhan_thoi_gian', $data)) {
            $raw = $data['nguoi_nhan_thoi_gian'];

            if ($raw === null || $raw === '') {
                $data['nguoi_nhan_thoi_gian'] = null;
            } else {
                try {
                    $dt = $raw instanceof \DateTimeInterface ? Carbon::instance($raw) : Carbon::parse($raw);
                    $data['nguoi_nhan_thoi_gian'] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $data['nguoi_nhan_thoi_gian'] = null;
                }
            }
        }
    }

    /**
     * Tạo mới dữ liệu (BÁO GIÁ SỰ KIỆN)
     */
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $tongTienHang = 0;

            // ===== GIÁ DỊCH VỤ: ƯU TIÊN don_gia, fallback gia_nhap_mac_dinh =====
            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                $soLuong = (int)($item['so_luong'] ?? 0);
                if ($soLuong <= 0) {
                    throw new Exception('Số lượng dịch vụ phải > 0');
                }

                $userPrice = isset($item['don_gia']) ? (int)$item['don_gia'] : null;

                $sanPham = SanPham::find($item['san_pham_id'] ?? null);
                if (! $sanPham && $userPrice === null) {
                    throw new Exception('Dịch vụ ID ' . ($item['san_pham_id'] ?? 'NULL') . ' không tồn tại');
                }

                if ($userPrice !== null && $userPrice >= 0) {
                    $usedPrice = $userPrice;
                } else {
                    $usedPrice = (int)($sanPham->gia_nhap_mac_dinh ?? 0);
                }

                if ($usedPrice < 0) {
                    $usedPrice = 0;
                }

                $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
                $data['danh_sach_san_pham'][$index]['thanh_tien'] = $soLuong * $usedPrice;
    // ===== SECTION & COST cho báo giá sự kiện =====

    // SECTION_CODE:
    // Ưu tiên: payload gửi lên (danh_sach_san_pham.*.section_code),
    // nếu không có thì lấy group_code từ danh mục dịch vụ (danhMuc.group_code).
    $sectionCode = $item['section_code'] ?? null;
    if ($sectionCode === null && $sanPham && $sanPham->danhMuc) {
        $sectionCode = $sanPham->danhMuc->group_code; // A/B/C/D
    }
    $data['danh_sach_san_pham'][$index]['section_code'] = $sectionCode;

    // TITLE:
    // Ưu tiên: payload gửi title riêng cho dòng,
    // nếu không thì dùng tên dịch vụ.
    if (isset($item['title']) && $item['title'] !== '') {
        $data['danh_sach_san_pham'][$index]['title'] = $item['title'];
    } else {
        $data['danh_sach_san_pham'][$index]['title'] = $sanPham ? $sanPham->ten_san_pham : null;
    }

    // DESCRIPTION:
    // Cho phép FE gửi mô tả chi tiết (nếu không gửi thì để null).
    if (isset($item['description']) && $item['description'] !== '') {
        $data['danh_sach_san_pham'][$index]['description'] = $item['description'];
    }

    // BASE COST & COST AMOUNT:
    // base_cost: giá vốn (mặc định = gia_nhap_mac_dinh, FE có thể override).
    $baseCost = isset($item['base_cost'])
        ? (int)$item['base_cost']
        : (int)($sanPham->gia_nhap_mac_dinh ?? 0);

    if ($baseCost < 0) {
        $baseCost = 0;
    }

    $data['danh_sach_san_pham'][$index]['base_cost']   = $baseCost;
    $data['danh_sach_san_pham'][$index]['cost_amount'] = $baseCost * $soLuong;

                // ===== GÓI DỊCH VỤ: lưu flag + tên hiển thị + chi tiết gói =====
                // FE sẽ gửi: is_package (bool), san_pham_label (tên gói), package_items (array)
                $isPackage = !empty($item['is_package']);
                $data['danh_sach_san_pham'][$index]['is_package'] = $isPackage;

                if ($isPackage) {
                    // ten_hien_thi: ưu tiên label FE gửi (tên gói), fallback tên sản phẩm
                    $tenHienThi = $item['san_pham_label']
                        ?? $item['title']
                        ?? ($sanPham->ten_san_pham ?? null);
                    $data['danh_sach_san_pham'][$index]['ten_hien_thi'] = $tenHienThi;

                    // package_items: FE gửi array, DB lưu JSON
                    $items = $item['package_items'] ?? null;
                    if (is_array($items)) {
                        $data['danh_sach_san_pham'][$index]['package_items'] = json_encode($items, JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($items)) {
                        // nếu FE lỡ gửi sẵn JSON string
                        $data['danh_sach_san_pham'][$index]['package_items'] = $items;
                    }
                } else {
                    // Dòng thường: nếu FE có gửi san_pham_label thì cũng lưu vào ten_hien_thi
                    if (!empty($item['san_pham_label'])) {
                        $data['danh_sach_san_pham'][$index]['ten_hien_thi'] = $item['san_pham_label'];
                    }
                }

                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];


                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            // ===== GIẢM GIÁ: THỦ CÔNG + THÀNH VIÊN =====
            $manualDiscount = (int)($data['giam_gia'] ?? 0);

            $memberPercent = 0.0;

            // Chỉ xét giảm giá thành viên khi:
            // - Đơn là KH hệ thống (loai_khach_hang = 0)
            // - Có khach_hang_id
            $loaiKh = (int)($data['loai_khach_hang'] ?? 0);
            $khId   = $data['khach_hang_id'] ?? null;

            $khTmp    = null;
            $isSystem = false;
            $isAgency = false;

            if (! empty($khId)) {
                $khTmp = KhachHang::find($khId);
                if ($khTmp) {
                    $isSystem = (bool) $khTmp->is_system_customer;
                    $isAgency = ($khTmp->customer_type === KhachHang::TYPE_AGENCY);
                }
            }

            // Chỉ KH hệ thống, KHÔNG phải Agency mới được giảm giá thành viên
            if ($loaiKh === 0 && $khTmp && $isSystem && ! $isAgency) {
                if (array_key_exists('giam_gia_thanh_vien', $data)
                    && $data['giam_gia_thanh_vien'] !== null
                    && $data['giam_gia_thanh_vien'] !== ''
                ) {
                    $memberPercent = (float)$data['giam_gia_thanh_vien'];
                    if ($memberPercent < 0) {
                        $memberPercent = 0;
                    } elseif ($memberPercent > 100) {
                        $memberPercent = 100;
                    }
                }
            } else {
                $memberPercent = 0.0;
            }

            $memberAmount = (int) round($tongTienHang * $memberPercent / 100);
            $totalDiscount = $manualDiscount + $memberAmount;

            $chiPhi  = (int)($data['chi_phi'] ?? 0);

            // ===== VAT-AWARE TOTALS =====
            $taxMode = (int)($data['tax_mode'] ?? 0);
            $vatRate = array_key_exists('vat_rate', $data) ? (float)$data['vat_rate'] : null;

            $subtotal = max(0, (int)$tongTienHang - $totalDiscount + $chiPhi);

            if ($taxMode === 1 && $vatRate !== null) {
                $vatAmount  = (int) round($subtotal * $vatRate / 100, 0);
                $grandTotal = $subtotal + $vatAmount;
            } else {
                $taxMode    = 0;
                $vatRate    = null;
                $vatAmount  = null;
                $grandTotal = $subtotal;
            }

            $tongTienCanThanhToan = (int) $grandTotal;

            $this->normalizePayments($data, $tongTienCanThanhToan);

            $data['tax_mode']    = $taxMode;
            $data['vat_rate']    = $vatRate;
            $data['subtotal']    = ($taxMode === 1) ? (int)$subtotal : null;
            $data['vat_amount']  = ($taxMode === 1) ? (int)$vatAmount : null;
            $data['grand_total'] = ($taxMode === 1) ? (int)$grandTotal : null;

            $data['member_discount_percent'] = (int)$memberPercent;
            $data['member_discount_amount']  = $memberAmount;

            $this->normalizeRecipientFields($data);

            // Trạng thái đơn hàng (giao hàng)
            if (! array_key_exists('trang_thai_don_hang', $data)
                || $data['trang_thai_don_hang'] === null
                || $data['trang_thai_don_hang'] === ''
            ) {
                $data['trang_thai_don_hang'] = DonHang::TRANG_THAI_CHUA_GIAO;
            } else {
                $v = (int)$data['trang_thai_don_hang'];
                $data['trang_thai_don_hang'] = in_array($v, [
                    DonHang::TRANG_THAI_CHUA_GIAO,
                    DonHang::TRANG_THAI_DANG_GIAO,
                    DonHang::TRANG_THAI_DA_GIAO,
                    DonHang::TRANG_THAI_DA_HUY,
                ], true) ? $v : DonHang::TRANG_THAI_CHUA_GIAO;
            }

            $data['tong_tien_hang']             = (int)$tongTienHang;
            $data['tong_tien_can_thanh_toan']   = (int)$tongTienCanThanhToan;
            $data['tong_so_luong_san_pham']     = count($data['danh_sach_san_pham']);

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai']  = $khachHang->so_dien_thoai;
                }
            }

            // ⚠️ KHÔNG nhận ma_don_hang từ request (BE tự sinh)
            $dataDonHang = $data;
            unset(
                $dataDonHang['danh_sach_san_pham'],
                $dataDonHang['so_tien_con_lai'],
                $dataDonHang['ma_don_hang'],
                $dataDonHang['giam_gia_thanh_vien'],
                $dataDonHang['khach_hang_display'],
                $dataDonHang['kenh_lien_he_display']
            );

            $donHang = DonHang::create($dataDonHang);

            if (empty($donHang->ma_don_hang)) {
                $donHang->ma_don_hang = 'DH' . str_pad((string)$donHang->id, 5, '0', STR_PAD_LEFT);
                $donHang->saveQuietly();
            }

            foreach ($data['danh_sach_san_pham'] as $item) {
                // Chuẩn hoá field cho bản ghi chi_tiet_don_hangs
                $clean = [
                    'don_hang_id'   => $donHang->id,
                    'san_pham_id'   => $item['san_pham_id'],
                    'don_vi_tinh_id'=> $item['don_vi_tinh_id'],
                    'so_luong'      => $item['so_luong'],
                    'don_gia'       => $item['don_gia'],
                    'thanh_tien'    => $item['thanh_tien'],
                    // Gói dịch vụ
                    'is_package'    => !empty($item['is_package']),
                    'ten_hien_thi'  => $item['ten_hien_thi'] ?? null,
                     'hang_muc_goc'  => $item['hang_muc_goc'] ?? null,
                       'package_items' => (function ($row) {
            // Không phải gói thì luôn null
            if (empty($row['is_package'])) {
                return null;
            }

            $val = $row['package_items'] ?? null;

            if (is_array($val)) {
                return json_encode($val, JSON_UNESCAPED_UNICODE);
            }

            // Nếu trước đó đã encode rồi (string JSON) → dùng lại
            if (is_string($val) && $val !== '') {
                return $val;
            }

            return null;
        })($item),

                ];

                // Nếu có nguoi_tao/nguoi_cap_nhat thì thêm (tuỳ bảng cho phép null hay không)
                if (!empty($dataDonHang['nguoi_tao'] ?? null)) {
                    $clean['nguoi_tao'] = $dataDonHang['nguoi_tao'];
                }
                if (!empty($dataDonHang['nguoi_cap_nhat'] ?? null)) {
                    $clean['nguoi_cap_nhat'] = $dataDonHang['nguoi_cap_nhat'];
                }

                ChiTietDonHang::create($clean);
            }


            DB::commit();
            return $donHang->refresh();
        } catch (Exception $e) {
            DB::rollBack();
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update($id, array $data)
    {
        DB::beginTransaction();

        $donHang = $this->getById($id);

        $isDelivered       = (int)$donHang->trang_thai_don_hang === DonHang::TRANG_THAI_DA_GIAO;
        $isPaidFull        = (int)$donHang->trang_thai_thanh_toan === 2
                             || (int)$donHang->so_tien_da_thanh_toan >= (int)$donHang->tong_tien_can_thanh_toan;
        $isOlderThan10Days = Carbon::parse($donHang->ngay_tao_don_hang)->diffInDays(Carbon::now()) > 10;

        $allowed = null;
        if ($isDelivered && $isPaidFull) {
            return CustomResponse::error('Đơn đã giao và đã thanh toán đủ — khoá toàn bộ chỉnh sửa.', 422);
} elseif ($isDelivered) {
    $allowed = [
        'loai_thanh_toan',
        'so_tien_da_thanh_toan',
        'ghi_chu',
        'quote_section_titles',
        'quote_footer_note',
    ];
} elseif ($isOlderThan10Days) {
    $allowed = [
        'trang_thai_don_hang',
        'nguoi_nhan_thoi_gian',
        'loai_thanh_toan',
        'so_tien_da_thanh_toan',
        'ghi_chu',
        'quote_section_titles',
        'quote_footer_note',
    ];
    if (! $isDelivered) {
        $allowed[] = 'dia_chi_giao_hang';
    }
}


        if (is_array($allowed)) {
            $data = array_intersect_key($data, array_flip($allowed));
        }

        try {
            $allowMoneyRecalc = array_key_exists('danh_sach_san_pham', $data)
                             || array_key_exists('giam_gia', $data)
                             || array_key_exists('chi_phi', $data)
                             || array_key_exists('tax_mode', $data)
                             || array_key_exists('vat_rate', $data);

            if ($allowMoneyRecalc) {
                $tongTienHang = 0;

                foreach ($data['danh_sach_san_pham'] as $index => $item) {
                    $soLuong = (int)($item['so_luong'] ?? 0);
                    if ($soLuong <= 0) {
                        throw new Exception('Số lượng dịch vụ phải > 0');
                    }

                    $userPrice = isset($item['don_gia']) ? (int)$item['don_gia'] : null;

                    $sanPham = SanPham::find($item['san_pham_id'] ?? null);
                    if (! $sanPham && $userPrice === null) {
                        throw new Exception('Dịch vụ ID ' . ($item['san_pham_id'] ?? 'NULL') . ' không tồn tại');
                    }

                    if ($userPrice !== null && $userPrice >= 0) {
                        $usedPrice = $userPrice;
                    } else {
                        $usedPrice = (int)($sanPham->gia_nhap_mac_dinh ?? 0);
                    }

                    if ($usedPrice < 0) {
                        $usedPrice = 0;
                    }

                    $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = $soLuong * $usedPrice;
                            $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
        $data['danh_sach_san_pham'][$index]['thanh_tien'] = $soLuong * $usedPrice;

        // ===== SECTION & COST cho báo giá sự kiện =====

        $sectionCode = $item['section_code'] ?? null;
        if ($sectionCode === null && $sanPham && $sanPham->danhMuc) {
            $sectionCode = $sanPham->danhMuc->group_code;
        }
        $data['danh_sach_san_pham'][$index]['section_code'] = $sectionCode;

        if (isset($item['title']) && $item['title'] !== '') {
            $data['danh_sach_san_pham'][$index]['title'] = $item['title'];
        } else {
            $data['danh_sach_san_pham'][$index]['title'] = $sanPham ? $sanPham->ten_san_pham : null;
        }

        if (isset($item['description']) && $item['description'] !== '') {
            $data['danh_sach_san_pham'][$index]['description'] = $item['description'];
        }

        $baseCost = isset($item['base_cost'])
            ? (int)$item['base_cost']
            : (int)($sanPham->gia_nhap_mac_dinh ?? 0);

        if ($baseCost < 0) {
            $baseCost = 0;
        }

        $data['danh_sach_san_pham'][$index]['base_cost']   = $baseCost;
        $data['danh_sach_san_pham'][$index]['cost_amount'] = $baseCost * $soLuong;


                // ===== GÓI DỊCH VỤ: lưu flag + tên hiển thị + chi tiết gói =====
                $isPackage = !empty($item['is_package']);
                $data['danh_sach_san_pham'][$index]['is_package'] = $isPackage;

                if ($isPackage) {
                    $tenHienThi = $item['san_pham_label']
                        ?? $item['title']
                        ?? ($sanPham->ten_san_pham ?? null);
                    $data['danh_sach_san_pham'][$index]['ten_hien_thi'] = $tenHienThi;

                    $items = $item['package_items'] ?? null;
                    if (is_array($items)) {
                        $data['danh_sach_san_pham'][$index]['package_items'] = json_encode($items, JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($items)) {
                        $data['danh_sach_san_pham'][$index]['package_items'] = $items;
                    }
                } else {
                    if (!empty($item['san_pham_label'])) {
                        $data['danh_sach_san_pham'][$index]['ten_hien_thi'] = $item['san_pham_label'];
                    }
                }

                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];


        $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];


                    $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
                }

                // ===== GIẢM GIÁ: THỦ CÔNG + THÀNH VIÊN (UPDATE) =====
                $manualDiscount = array_key_exists('giam_gia', $data)
                    ? (int)$data['giam_gia']
                    : (int)($donHang->giam_gia ?? 0);

                $chiPhi = array_key_exists('chi_phi', $data)
                    ? (int)$data['chi_phi']
                    : (int)($donHang->chi_phi ?? 0);

                $loaiKh = array_key_exists('loai_khach_hang', $data)
                    ? (int)$data['loai_khach_hang']
                    : (int)($donHang->loai_khach_hang ?? 0);

                $khId = array_key_exists('khach_hang_id', $data)
                    ? $data['khach_hang_id']
                    : $donHang->khach_hang_id;

                $memberPercent = 0.0;

                $khTmp    = null;
                $isSystem = false;
                $isAgency = false;

                if (! empty($khId)) {
                    if ($donHang->khach_hang_id == $khId && $donHang->relationLoaded('khachHang')) {
                        $khTmp = $donHang->khachHang;
                    } else {
                        $khTmp = KhachHang::find($khId);
                    }

                    if ($khTmp) {
                        $isSystem = (bool) $khTmp->is_system_customer;
                        $isAgency = ($khTmp->customer_type === KhachHang::TYPE_AGENCY);
                    }
                }

                if ($loaiKh === 0 && $khTmp && $isSystem && ! $isAgency) {
                    if (array_key_exists('giam_gia_thanh_vien', $data)
                        && $data['giam_gia_thanh_vien'] !== null
                        && $data['giam_gia_thanh_vien'] !== ''
                    ) {
                        $memberPercent = (float)$data['giam_gia_thanh_vien'];
                    } else {
                        $memberPercent = (float)($donHang->member_discount_percent ?? 0);
                    }

                    if ($memberPercent < 0) {
                        $memberPercent = 0;
                    } elseif ($memberPercent > 100) {
                        $memberPercent = 100;
                    }
                } else {
                    $memberPercent = 0.0;
                }

                $memberAmount = (int) round($tongTienHang * $memberPercent / 100);

                $totalDiscount = $manualDiscount + $memberAmount;

                $taxMode = array_key_exists('tax_mode', $data)
                    ? (int)$data['tax_mode']
                    : (int)($donHang->tax_mode ?? 0);

                $vatRate = array_key_exists('vat_rate', $data)
                    ? (float)$data['vat_rate']
                    : ($donHang->vat_rate !== null ? (float)$donHang->vat_rate : null);

                $subtotal = max(0, (int)$tongTienHang - $totalDiscount + $chiPhi);

                if ($taxMode === 1 && $vatRate !== null) {
                    $vatAmount  = (int) round($subtotal * $vatRate / 100, 0);
                    $grandTotal = $subtotal + $vatAmount;
                } else {
                    $taxMode    = 0;
                    $vatRate    = null;
                    $vatAmount  = null;
                    $grandTotal = $subtotal;
                }

                $tongTienCanThanhToan = (int) $grandTotal;

                $this->normalizePayments($data, $tongTienCanThanhToan);

                $data['tong_tien_hang']           = (int)$tongTienHang;
                $data['tong_tien_can_thanh_toan'] = (int)$tongTienCanThanhToan;
                $data['tong_so_luong_san_pham']   = isset($data['danh_sach_san_pham'])
                    ? count($data['danh_sach_san_pham'])
                    : $donHang->tong_so_luong_san_pham;

                $data['tax_mode']   = $taxMode;
                $data['vat_rate']   = $vatRate;
                $data['subtotal']   = ($taxMode === 1) ? (int)$subtotal : null;
                $data['vat_amount'] = ($taxMode === 1) ? (int)$vatAmount : null;
                $data['grand_total']= ($taxMode === 1) ? (int)$grandTotal : null;

                $data['member_discount_percent'] = (int)$memberPercent;
                $data['member_discount_amount']  = $memberAmount;

            } else {
                $this->normalizePayments($data, (int) $donHang->tong_tien_can_thanh_toan);
            }

            $this->normalizeRecipientFields($data);

            if (array_key_exists('trang_thai_don_hang', $data)) {
                if ($data['trang_thai_don_hang'] === null || $data['trang_thai_don_hang'] === '') {
                    $data['trang_thai_don_hang'] = DonHang::TRANG_THAI_CHUA_GIAO;
                } else {
                    $v = (int)$data['trang_thai_don_hang'];
                    $data['trang_thai_don_hang'] = in_array($v, [
                        DonHang::TRANG_THAI_CHUA_GIAO,
                        DonHang::TRANG_THAI_DANG_GIAO,
                        DonHang::TRANG_THAI_DA_GIAO,
                        DonHang::TRANG_THAI_DA_HUY,
                    ], true) ? $v : DonHang::TRANG_THAI_CHUA_GIAO;
                }
            }

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai']  = $khachHang->so_dien_thoai;
                }
            }

            $dataDonHang = $data;
            unset(
                $dataDonHang['danh_sach_san_pham'],
                $dataDonHang['so_tien_con_lai'],
                $dataDonHang['ma_don_hang'],
                $dataDonHang['giam_gia_thanh_vien'],
                $dataDonHang['khach_hang_display'],
                $dataDonHang['kenh_lien_he_display']
            );

            $donHang->update($dataDonHang);

            if (isset($data['danh_sach_san_pham']) && is_array($data['danh_sach_san_pham'])) {
                $donHang->chiTietDonHangs()->delete();

                foreach ($data['danh_sach_san_pham'] as $item) {
                    $clean = [
                        'don_hang_id'   => $donHang->id,
                        'san_pham_id'   => $item['san_pham_id'],
                        'don_vi_tinh_id'=> $item['don_vi_tinh_id'],
                        'so_luong'      => $item['so_luong'],
                        'don_gia'       => $item['don_gia'],
                        'thanh_tien'    => $item['thanh_tien'],
                        'is_package'    => !empty($item['is_package']),
                        'ten_hien_thi'  => $item['ten_hien_thi'] ?? null,
                          'hang_muc_goc'  => $item['hang_muc_goc'] ?? null,
                                   'package_items' => (function ($row) {
                if (empty($row['is_package'])) {
                    return null;
                }

                $val = $row['package_items'] ?? null;

                if (is_array($val)) {
                    return json_encode($val, JSON_UNESCAPED_UNICODE);
                }

                if (is_string($val) && $val !== '') {
                    return $val;
                }

                return null;
            })($item),

                    ];

                    if (!empty($dataDonHang['nguoi_tao'] ?? null)) {
                        $clean['nguoi_tao'] = $dataDonHang['nguoi_tao'];
                    }
                    if (!empty($dataDonHang['nguoi_cap_nhat'] ?? null)) {
                        $clean['nguoi_cap_nhat'] = $dataDonHang['nguoi_cap_nhat'];
                    }

                    ChiTietDonHang::create($clean);
                }
            }


            DB::commit();
            return $donHang->refresh();
        } catch (Exception $e) {
            DB::rollBack();
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $donHang = $this->getById($id);

            if ($donHang->phieuThu()->exists() || $donHang->chiTietPhieuThu()->exists()) {
                throw new Exception('Đơn hàng đã có phiếu thu, không thể xóa');
            }

            $donHang->chiTietDonHangs()->delete();

            return $donHang->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách QuanLyBanHang dạng option
     */
    public function getOptions(array $params = [])
    {
        $query = DonHang::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['don_hangs.id as value', 'don_hangs.ma_don_hang as label']
        );

        return $result['collection'];
    }

    /**
     * Lấy giá bán sản phẩm (API phụ, giữ tạm cho tương thích)
     */
    public function getGiaBanSanPham($sanPhamId, $donViTinhId, $loaiGia = 1)
    {
        // Ưu tiên giá theo lô
        $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $sanPhamId)
            ->where('don_vi_tinh_id', $donViTinhId)
            ->orderBy('id', 'asc')
            ->first();

        if ($loSanPham) {
            return (int)$loSanPham->gia_ban_le_don_vi;
        }

        $sanPham = SanPham::find($sanPhamId);
        if ($sanPham) {
            $base = (int)($loaiGia == 2
                ? ($sanPham->gia_dat_truoc_3n ?? 0)
                : ($sanPham->gia_nhap_mac_dinh ?? 0));

            return $base;
        }

        return null;
    }

    /**
     * Xem trước hóa đơn (HTML)
     */
    public function xemTruocHoaDon($id)
    {
        try {
            $donHang = $this->getById($id);

            if (! $donHang) {
                return CustomResponse::error('Đơn hàng không tồn tại');
            }

            return view('hoa-don.template', compact('donHang'));
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xem trước hóa đơn: ' . $e->getMessage());
        }
    }

    public function getSanPhamByDonHangId($donHangId)
    {
        return DonHang::with('chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')
            ->where('id', $donHangId)
            ->first();
    }

    public function getDonHangByKhachHangId($khachHangId)
    {
        return DonHang::with('khachHang')
            ->where('khach_hang_id', $khachHangId)
            ->where('trang_thai_thanh_toan', 0)
            ->get();
    }

    public function getSoTienCanThanhToan($donHangId)
    {
        $donHang = $this->getById($donHangId);
        return (int)$donHang->tong_tien_can_thanh_toan - (int)$donHang->so_tien_da_thanh_toan;
    }
}
