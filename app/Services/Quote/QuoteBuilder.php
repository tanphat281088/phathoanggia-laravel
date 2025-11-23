<?php

namespace App\Services\Quote;

use App\Models\DonHang;
use App\Models\DanhMucSanPham;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * QuoteBuilder
 *
 * Nhiệm vụ:
 *  - Nhận 1 đơn hàng (DonHang) + các chi tiết.
 *  - Đọc cây danh mục dịch vụ / group_code.
 *  - Gom thành các SECTION: A. Nhân sự, B. Cơ sở vật chất, C. Tiệc, D. Thuê địa điểm, E. Chi phí khác...
 *  - Mỗi SECTION chứa list dòng báo giá (QuoteLine) với:
 *      + hang_muc   (tầng 1)
 *      + chi_tiet   (chi tiết dịch vụ / hoặc list chi tiết gói)
 *      + dvt, so_luong, gia_goc, chi_phi, don_gia, thanh_tien
 */
class QuoteBuilder
{
    /**
     * Build dữ liệu báo giá cho 1 đơn hàng.
     *
     * Trả về:
     *  [
     *      'sections' => [ ... ],
     *      'totals'   => [
     *          'total_chi_phi'    => int,
     *          'total_thanh_tien' => int,
     *      ],
     *  ]
     */
    public function buildForDonHang(DonHang $donHang): array
    {
        $lines = $this->buildLinesFromDonHang($donHang);

        // Gom group theo section_key (NS/CSVC/TIEC/TD/CPK/KHAC...)
        $sectionOrder = ['NS', 'CSVC', 'TIEC', 'TD', 'CPK', 'KHAC'];

        /** @var Collection $grouped */
        $grouped = $lines->groupBy('section_key')
            ->sortBy(function (Collection $items, string $key) use ($sectionOrder) {
                $idx = array_search($key, $sectionOrder, true);
                return $idx === false ? 999 : $idx;
            });

        $sections = [];
        $letters  = range('A', 'Z');
        $i        = 0;

        $totalChiPhi    = 0;
        $totalThanhTien = 0;

        foreach ($grouped as $sectionKey => $items) {
            /** @var Collection $items */
            if ($items->isEmpty()) {
                continue;
            }

            $letter = $letters[$i] ?? '';
            $name   = (string) ($items->first()['section_name'] ?? $sectionKey);

            $sumChiPhi    = (int) $items->sum('chi_phi');
            $sumThanhTien = (int) $items->sum('thanh_tien');

            $totalChiPhi    += $sumChiPhi;
            $totalThanhTien += $sumThanhTien;

            $sections[] = [
                'key'               => $sectionKey,
                'letter'            => $letter,
                'name'              => $name,
                'items'             => $items->values()->all(),
                'total_chi_phi'     => $sumChiPhi,
                'total_thanh_tien'  => $sumThanhTien,
            ];

            $i++;
        }

        return [
            'sections' => $sections,
            'totals'   => [
                'total_chi_phi'    => $totalChiPhi,
                'total_thanh_tien' => $totalThanhTien,
            ],
        ];
    }

    /**
     * Build toàn bộ các dòng báo giá (QuoteLine) từ chi tiết đơn hàng.
     *
     * Mỗi dòng có keys:
     *  - section_key, section_name
     *  - hang_muc
     *  - chi_tiet, chi_tiet_html (HTML nhiều dòng cho gói)
     *  - dvt, so_luong
     *  - gia_goc_don_vi, chi_phi (chi phí nội bộ – dùng cho tính toán, KH không thấy cột)
     *  - don_gia, thanh_tien
     */
    protected function buildLinesFromDonHang(DonHang $donHang): Collection
    {
        $lines = [];

        $chiTiets = $donHang->chiTietDonHangs ?? collect();

        foreach ($chiTiets as $ct) {
            $sanPham = $ct->sanPham ?? null;

            // ===== GÓI DỊCH VỤ & package_items =====
            $isPackage        = (bool)($ct->is_package ?? false);
            $packageItemsRaw  = $ct->package_items ?? null;
            $packageItems     = [];

            if ($packageItemsRaw) {
                if (is_array($packageItemsRaw)) {
                    $packageItems = $packageItemsRaw;
                } else {
                    try {
                        $decoded = json_decode($packageItemsRaw, true);
                        if (is_array($decoded)) {
                            $packageItems = $decoded;
                        }
                    } catch (\Throwable $e) {
                        $packageItems = [];
                    }
                }
            }

            // ===== Lấy thông tin danh mục & group_code =====
            $catInfo = $this->resolveCategoryInfo($sanPham);

            $groupCodeNorm = $catInfo['group_code'] ?? null;
            if (!$groupCodeNorm) {
                $groupCodeNorm = 'KHAC';
            }

                        $sectionName = $this->mapGroupCodeToSectionName($groupCodeNorm);

            // Tầng 1 (HẠNG MỤC):
            // 🔹 Ưu tiên hang_muc_goc (Nhóm gói dịch vụ mà anh đã chọn trong modal)
            // 🔹 Nếu chưa có (báo giá cũ) thì fallback về danh mục sản phẩm như cũ
            $hangMuc = $ct->hang_muc_goc
                ?? $catInfo['level1_name']
                ?? $catInfo['level2_name']
                ?? $sectionName;


            // ===== Chi tiết: nếu là GÓI → list chi tiết dịch vụ con; nếu không → tên dịch vụ =====
            $chiTiet     = null;   // text thuần
            $chiTietHtml = null;   // HTML nhiều dòng (cho Blade)

            if ($isPackage && !empty($packageItems)) {
                $partsText = [];
                $partsHtml = [];

                foreach ($packageItems as $pi) {
                    $name = $pi['ten_san_pham'] ?? '';
                    $qty  = $pi['so_luong'] ?? null;
                    $note = $pi['ghi_chu'] ?? '';

                    if (!$name) {
                        continue;
                    }

                    // ===== Định dạng: 02 Loa..., 01 Mixer..., =====
                    $label = $name;

                    if ($qty !== null && $qty !== '') {
                        $n = (float)$qty;
                        if ($n > 0) {
                            $qtyInt  = (int) round($n);
                            $qtyText = str_pad((string)$qtyInt, 2, '0', STR_PAD_LEFT);
                            $label   = $qtyText . ' ' . $name;
                        }
                    }

                    if ($note) {
                        $label .= ' (' . $note . ')';
                    }

                    $partsText[] = $label;
                    $partsHtml[] = e($label);
                }

                if (!empty($partsText)) {
                    $chiTiet     = implode(' • ', $partsText);
                    $chiTietHtml = implode('<br>', $partsHtml);
                }
            }

            // Nếu chưa có chi tiết từ gói, fallback: ten_hien_thi → tên dịch vụ
            if (!$chiTiet) {
                if (!empty($ct->ten_hien_thi)) {
                    $chiTiet = (string)$ct->ten_hien_thi;
                } elseif ($sanPham) {
                    $chiTiet = $sanPham->ten_san_pham
                        ?? $sanPham->ten_dich_vu
                        ?? $sanPham->name
                        ?? null;
                }
            }

            if (!$chiTiet) {
                $chiTiet = 'Dịch vụ';
            }

            // ===== Đơn vị tính =====
            $dvtModel = $ct->donViTinh ?? null;
            $dvt = $dvtModel->ten_don_vi
                ?? $dvtModel->name
                ?? $ct->don_vi_tinh
                ?? '';

            // Số lượng
            $soLuong = (float) ($ct->so_luong ?? 0);

            // Giá gốc / chi phí nội bộ
            $giaGocDonVi = 0.0;
            if ($sanPham) {
                $giaGocDonVi =
                    (float) (
                        $sanPham->gia_nhap_mac_dinh
                        ?? $sanPham->gia_goc
                        ?? 0
                    );
            }
            $chiPhi = $giaGocDonVi * $soLuong;

            // Đơn giá bán + thành tiền
            $donGia    = (float) ($ct->don_gia ?? 0);
            $thanhTien = (float) ($ct->thanh_tien ?? ($donGia * $soLuong));

                $lines[] = [
                'section_key'     => $groupCodeNorm,
                'section_name'    => $sectionName,

                'hang_muc'        => $hangMuc,
                'hang_muc_goc'    => $ct->hang_muc_goc,
                'is_package'      => (bool) ($ct->is_package ?? false), // 🔹 THÊM DÒNG NÀY

                'chi_tiet'        => $chiTiet,
                'chi_tiet_html'   => $chiTietHtml,  // dùng trong Blade để xuống dòng
                'dvt'             => $dvt,
                'so_luong'        => $soLuong,

                // Chi phí nội bộ (vẫn giữ để tính tổng nội bộ, KH không thấy cột)
                'gia_goc_don_vi'  => (int) round($giaGocDonVi),
                'chi_phi'         => (int) round($chiPhi),

                'don_gia'         => (int) round($donGia),
                'thanh_tien'      => (int) round($thanhTien),
            ];

        }

        return collect($lines);
    }

    /**
     * Lấy thông tin danh mục/tầng & group_code từ sản phẩm.
     */
    protected function resolveCategoryInfo($sanPham): array
    {
        $groupCode  = null;
        $level1Name = null;
        $level2Name = null;

        if (!$sanPham) {
            return [
                'group_code'  => null,
                'level1_name' => null,
                'level2_name' => null,
            ];
        }

        // Trường group_code trực tiếp trên sản phẩm (nếu có)
        $groupCode = Arr::get($sanPham, 'group_code');

        // Thử lấy danh mục chính (tầng 2) từ nhiều kiểu key khác nhau
        $danhMucId = Arr::get($sanPham, 'danh_muc_san_pham_id')
            ?? Arr::get($sanPham, 'danh_muc_id')
            ?? null;

        $cat = null;
        if ($danhMucId) {
            $cat = DanhMucSanPham::find($danhMucId);
        }

        if ($cat) {
            $level2Name = $cat->ten_danh_muc ?? null;
            if (!$groupCode && !empty($cat->group_code)) {
                $groupCode = $cat->group_code;
            }

            if (!empty($cat->parent_id)) {
                $parent = DanhMucSanPham::find($cat->parent_id);
                if ($parent) {
                    $level1Name = $parent->ten_danh_muc ?? null;
                    if (!$groupCode && !empty($parent->group_code)) {
                        $groupCode = $parent->group_code;
                    }
                }
            }
        }

        // Chuẩn hoá group_code về dạng ngắn
        $groupCodeNorm = $this->normalizeGroupCode($groupCode);

        return [
            'group_code'  => $groupCodeNorm,
            'level1_name' => $level1Name,
            'level2_name' => $level2Name,
        ];
    }

    /**
     * Chuẩn hoá group_code dài/ ngắn → code ngắn.
     */
    protected function normalizeGroupCode(?string $groupCode): ?string
    {
        if (!$groupCode) {
            return null;
        }

        $groupCode = strtoupper(trim($groupCode));

        $map = [
            'NS'             => 'NS',
            'NHAN_SU'        => 'NS',

            'CSVC'           => 'CSVC',
            'CO_SO_VAT_CHAT' => 'CSVC',

            'TIEC'           => 'TIEC',

            'TD'             => 'TD',
            'THUE_DIA_DIEM'  => 'TD',

            'CPK'            => 'CPK',
            'CHI_PHI_KHAC'   => 'CPK',
        ];

        return $map[$groupCode] ?? null;
    }

    /**
     * Map group_code ngắn → tên section hiển thị.
     */
    protected function mapGroupCodeToSectionName(string $groupCode): string
    {
        $groupCode = strtoupper($groupCode);

        $map = [
            'NS'   => 'Nhân sự',
            'CSVC' => 'Cơ sở vật chất',
            'TIEC' => 'Tiệc',
            'TD'   => 'Thuê địa điểm',
            'CPK'  => 'Chi phí khác',
            'KHAC' => 'Khác',
        ];

        return $map[$groupCode] ?? $groupCode;
    }

    /**
     * Xác định SUPPLIER cho dòng báo giá (hiện KHÔNG in ra cột).
     */
    protected function resolveSupplierName($sanPham): string
    {
        if (!$sanPham) {
            return 'PHG';
        }

        // Nếu có field kiểu "supplier_name" hoặc "nha_cung_cap"
        $supplier = Arr::get($sanPham, 'supplier_name')
            ?? Arr::get($sanPham, 'ten_nha_cung_cap')
            ?? Arr::get($sanPham, 'nha_cung_cap')
            ?? null;

        if ($supplier) {
            return (string) $supplier;
        }

        // Nếu có field loại dịch vụ (tự cung cấp / thuê ngoài) thì map ra text
        $loai = Arr::get($sanPham, 'loai_dich_vu');
        if ($loai) {
            $loai = strtoupper((string) $loai);
            if (in_array($loai, ['THUE_NGOAI', 'OUTSOURCE'], true)) {
                return 'Thuê ngoài';
            }
            if (in_array($loai, ['TU_CUNG_CAP', 'INHOUSE'], true)) {
                return 'PHG';
            }
        }

        return 'PHG';
    }
}
