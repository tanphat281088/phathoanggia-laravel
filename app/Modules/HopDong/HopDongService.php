<?php

namespace App\Modules\HopDong;

use App\Class\CustomResponse;
use App\Models\DonHang;
use App\Models\HopDong;
use App\Models\HopDongItem;
use App\Services\Quote\QuoteBuilder;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HopDongService
{
    protected QuoteBuilder $quoteBuilder;

    public function __construct(QuoteBuilder $quoteBuilder)
    {
        $this->quoteBuilder = $quoteBuilder;
    }

    /**
     * Tạo (hoặc lấy lại) Hợp đồng từ 1 Báo giá (DonHang)
     *
     * - Nếu đã có hop_dongs.don_hang_id = $donHangId -> trả về record cũ
     * - Nếu chưa có:
     *      + Snapshot thông tin Bên A, Bên B, Sự kiện, Giá trị HĐ, Thanh toán
     *      + Build danh sách hạng mục (HopDongItem) từ báo giá (QuoteBuilder)
     *      + Khởi tạo body_json theo template mặc định (Level 2)
     */
    public function createFromDonHang(int $donHangId)
    {
        try {
            return DB::transaction(function () use ($donHangId) {
                /** @var \App\Models\DonHang|null $donHang */
                $donHang = DonHang::with([
                    'khachHang',
                    'chiTietDonHangs.sanPham.danhMuc',
                    'chiTietDonHangs.donViTinh',
                ])->find($donHangId);

                if (! $donHang) {
                    return CustomResponse::error('Báo giá không tồn tại (don_hang_id không hợp lệ)');
                }

                // Nếu đã có Hợp đồng cho báo giá này → trả về luôn
                $existing = HopDong::where('don_hang_id', $donHang->id)->first();
                if ($existing) {
                    // Nếu HĐ cũ chưa có body_json (trước đây chưa dùng Level 2) → khởi tạo từ template
                    if (empty($existing->body_json)) {
                        $templateBlocks = config('hop_dong_template.blocks', []);
                        $existing->body_json = is_array($templateBlocks) ? $templateBlocks : [];
                        $existing->save();
                    }

                    // Đảm bảo đã load items
                    return $existing->load('items');
                }

                // ===== SNAPSHOT BÊN A (KHÁCH HÀNG) =====
                $kh = $donHang->khachHang;
                $benATen       = $kh->ten_khach_hang ?? $donHang->ten_khach_hang;
                $benADiaChi    = $kh->dia_chi ?? $donHang->dia_chi_giao_hang;
                $benAMst       = $kh->ma_so_thue ?? $kh->mst ?? null;
                $benADaiDien   = $kh->nguoi_dai_dien ?? null;
                $benAChucVu    = $kh->chuc_vu_dai_dien ?? null;
                $benADienThoai = $kh->so_dien_thoai ?? $donHang->so_dien_thoai ?? null;
                $benAEmail     = $kh->email ?? null;

      // ===== SNAPSHOT BÊN B (PHÁT HOÀNG GIA) – giống báo giá =====
$benBTen       = 'CÔNG TY TNHH SỰ KIỆN PHÁT HOÀNG GIA';
$benBDiaChi    = 'Văn phòng: 102 Nguyễn Minh Hoàng, Phường Bảy Hiền, Thành phố Hồ Chí Minh, Việt Nam';
$benBMst       = '0311465079';
// Tuỳ nhu cầu, anh có thể cập nhật lại chuỗi này
$benBTaiKhoan  = null;
$benBNganHang  = null;

// Xưng hô & người đại diện Bên B
$benBXungHo    = 'Ông';
$benBDaiDien   = 'Trần Tấn Phát';
$benBChucVu    = 'Giám đốc';


                // ===== THÔNG TIN SỰ KIỆN =====
                $suKienTen = $donHang->project_name
                    ?? $donHang->event_type
                    ?? 'Chương trình / Sự kiện';

                // Thời gian tổ chức (text) – ưu tiên event_start / event_end
                $timeText = '';
                if ($donHang->event_start) {
                    /** @var Carbon $start */
                    $start = $donHang->event_start instanceof Carbon
                        ? $donHang->event_start
                        : Carbon::parse($donHang->event_start);

                    $timeText = 'Ngày ' . $start->format('d/m/Y');
                    if ($donHang->event_end) {
                        $end = $donHang->event_end instanceof Carbon
                            ? $donHang->event_end
                            : Carbon::parse($donHang->event_end);
                        $timeText .= ', từ ' . $start->format('H\hi') . ' đến ' . $end->format('H\hi');
                    } else {
                        $timeText .= ', thời gian: ' . $start->format('H\hi');
                    }
                }

                $suKienDiaDiem = $donHang->venue_name
                    ? trim($donHang->venue_name . ' - ' . ($donHang->venue_address ?? ''))
                    : ($donHang->venue_address ?? $donHang->dia_chi_giao_hang ?? null);

                // ===== GIÁ TRỊ HỢP ĐỒNG (snapshot từ báo giá) =====
                $tongTruocVat = (int) ($donHang->subtotal ?? 0);
                $vatRate      = $donHang->vat_rate !== null ? (float) $donHang->vat_rate : null;
                $vatAmount    = (int) ($donHang->vat_amount ?? 0);
                // Ưu tiên tong_tien_can_thanh_toan nếu có
                $tongSauVat   = (int) ($donHang->tong_tien_can_thanh_toan
                    ?? $donHang->grand_total
                    ?? ($tongTruocVat + $vatAmount));

                // Chuyển số -> chữ (reuse logic như trong template báo giá nếu anh có helper)
                $tongSauVatBangChu = $this->vnNumberToText($tongSauVat);

                // ===== THANH TOÁN (mặc định 2 đợt 50% / 50%) =====
                $dot1TyLe        = 50;
                $dot1SoTien      = (int) round($tongSauVat * 0.5);
                $dot1ThoiDiemTxt = 'Trước khi thực hiện chương trình (chuyển khoản hoặc tiền mặt)';

                $dot2TyLe        = 50;
                $dot2SoTien      = max(0, $tongSauVat - $dot1SoTien);
                $dot2ThoiDiemTxt = 'Ngay sau khi kết thúc chương trình và nhận đầy đủ chứng từ Hợp đồng';

                // ===== TEMPLATE MẶC ĐỊNH (body_json) =====
                $templateBlocks = config('hop_dong_template.blocks', []);

                // ===== TẠO HỢP ĐỒNG HEADER =====
                /** @var HopDong $hopDong */
                $hopDong = HopDong::create([
                    'don_hang_id' => $donHang->id,

                    'so_hop_dong'   => null, // sẽ auto-gen trong model nếu null
                    'ngay_hop_dong' => Carbon::now(),

                    'status'        => HopDong::STATUS_DRAFT,

                    'ben_a_ten'         => $benATen,
                    'ben_a_dia_chi'     => $benADiaChi,
                    'ben_a_mst'         => $benAMst,
                    'ben_a_dai_dien'    => $benADaiDien,
                    'ben_a_chuc_vu'     => $benAChucVu,
                    'ben_a_dien_thoai'  => $benADienThoai,
                    'ben_a_email'       => $benAEmail,
    'ben_a_xung_ho'     => null, // để trống, user chọn Ông/Bà ở màn sửa

                    'ben_b_ten'         => $benBTen,
                    'ben_b_dia_chi'     => $benBDiaChi,
                    'ben_b_mst'         => $benBMst,
                    'ben_b_tai_khoan'   => $benBTaiKhoan,
                    'ben_b_ngan_hang'   => $benBNganHang,
                    'ben_b_dai_dien'    => $benBDaiDien,
                    'ben_b_chuc_vu'     => $benBChucVu,
    'ben_b_xung_ho'     => $benBXungHo,

                    'su_kien_ten'                   => $suKienTen,
                    'su_kien_thoi_gian_text'        => $timeText,
                    'su_kien_thoi_gian_setup_text'  => null,
                    'su_kien_dia_diem'              => $suKienDiaDiem,

                    'tong_truoc_vat'            => $tongTruocVat,
                    'vat_rate'                  => $vatRate,
                    'vat_amount'                => $vatAmount,
                    'tong_sau_vat'              => $tongSauVat,
                    'tong_sau_vat_bang_chu'     => $tongSauVatBangChu,

                    'dot1_ty_le'        => $dot1TyLe,
                    'dot1_so_tien'      => $dot1SoTien,
                    'dot1_thoi_diem_text' => $dot1ThoiDiemTxt,
                    'dot2_ty_le'        => $dot2TyLe,
                    'dot2_so_tien'      => $dot2SoTien,
                    'dot2_thoi_diem_text' => $dot2ThoiDiemTxt,

                    'dieukhoan_tuy_chinh' => null,

                    // TEMPLATE LEVEL 2: toàn bộ nội dung HĐ (có thể chỉnh sửa từng block)
                    'body_json'           => is_array($templateBlocks) ? $templateBlocks : [],

                    'nguoi_tao'           => (string) (Auth::id() ?? ''),
                    'nguoi_cap_nhat'      => (string) (Auth::id() ?? ''),
                ]);

                // ===== BUILD CHI TIẾT HỢP ĐỒNG TỪ BÁO GIÁ =====
                $this->buildItemsFromQuote($hopDong, $donHang);

                return $hopDong->load('items');
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi tạo Hợp đồng từ báo giá: ' . $e->getMessage());
        }
    }

    /**
     * Lấy chi tiết 1 Hợp đồng theo ID
     */
    public function getById(int $id)
    {
        $hopDong = HopDong::with([
            'donHang',
            'items',
        ])->find($id);

        if (! $hopDong) {
            return CustomResponse::error('Hợp đồng không tồn tại');
        }

        // Nếu HĐ cũ chưa có body_json (tạo trước khi triển khai Level 2) → khởi tạo từ template
        if (empty($hopDong->body_json)) {
            $templateBlocks = config('hop_dong_template.blocks', []);
            $hopDong->body_json = is_array($templateBlocks) ? $templateBlocks : [];
            $hopDong->save();
            $hopDong->refresh();
        }

        return $hopDong;
    }

    /**
     * Cập nhật thông tin Hợp đồng
     * - Cho phép sửa header (Bên A/B, sự kiện, thanh toán, điều khoản, body_json)
     * - Không đụng đến items ở phase 1 (giữ khớp báo giá)
     */
    public function update(int $id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                /** @var HopDong $hopDong */
                $hopDong = HopDong::findOrFail($id);

                // Không cho FE sửa các field này
                unset(
                    $data['id'],
                    $data['don_hang_id'],
                    $data['items'],
                    $data['created_at'],
                    $data['updated_at']
                );

                // Chuẩn hoá ngày hợp đồng (nếu FE gửi string)
                if (isset($data['ngay_hop_dong']) && $data['ngay_hop_dong']) {
                    $data['ngay_hop_dong'] = Carbon::parse($data['ngay_hop_dong'])
                        ->format('Y-m-d');
                }

                // Chuẩn hoá VAT
                if (array_key_exists('vat_rate', $data)) {
                    $data['vat_rate'] = $data['vat_rate'] !== null
                        ? (float) $data['vat_rate']
                        : null;
                }

                // Chuẩn hoá % & số tiền đợt thanh toán (để int)
                foreach (['dot1', 'dot2'] as $dot) {
                    $tyLeKey   = $dot . '_ty_le';
                    $soTienKey = $dot . '_so_tien';

                    if (isset($data[$tyLeKey])) {
                        $data[$tyLeKey] = (int) $data[$tyLeKey];
                    }
                    if (isset($data[$soTienKey])) {
                        $data[$soTienKey] = (int) $data[$soTienKey];
                    }
                }

                // Chuẩn hoá body_json: FE có thể gửi array hoặc JSON string
                if (array_key_exists('body_json', $data)) {
                    if (is_string($data['body_json'])) {
                        $decoded = json_decode($data['body_json'], true);
                        $data['body_json'] = is_array($decoded) ? $decoded : null;
                    } elseif (! is_array($data['body_json'])) {
                        $data['body_json'] = null;
                    }
                }

                // Tự động tính lại số tiền các đợt thanh toán nếu có % và tổng HĐ
                $this->recalcPaymentFromPercent($hopDong, $data);

                $data['nguoi_cap_nhat'] = (string) (Auth::id() ?? $hopDong->nguoi_cap_nhat);

                $hopDong->update($data);

                return $hopDong->fresh(['donHang', 'items']);
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi cập nhật Hợp đồng: ' . $e->getMessage());
        }
    }

    /**
     * Dựng danh sách HopDongItem từ báo giá (DonHang) thông qua QuoteBuilder
     * - sections A/B/C/D... khớp với báo giá
     */
    protected function buildItemsFromQuote(HopDong $hopDong, DonHang $donHang): void
    {
        // Xoá items cũ nếu có
        HopDongItem::where('hop_dong_id', $hopDong->id)->delete();

        $lines = $this->quoteBuilder->buildLinesForEditor($donHang);

        // Group theo section_key, sort theo thứ tự A,B,C...
        $sectionOrder = ['NS', 'CSVC', 'TIEC', 'TD', 'CPK', 'KHAC'];
        $sections = $lines->groupBy('section_key')
            ->sortBy(function ($items, $key) use ($sectionOrder) {
                $idx = array_search($key, $sectionOrder, true);
                return $idx === false ? 999 : $idx;
            });

        $letters = range('A', 'Z');
        $secIdx  = 0;

        foreach ($sections as $secCode => $items) {
            if ($items->isEmpty()) {
                continue;
            }

            $letter = $letters[$secIdx] ?? '';
            $secIdx++;

            foreach ($items as $line) {
                HopDongItem::create([
                    'hop_dong_id'          => $hopDong->id,
                    'chi_tiet_don_hang_id' => $line['chi_tiet_don_hang_id'] ?? null,
                    'section_code'         => $line['section_key'] ?? null,
                    'section_letter'       => $letter,

                    'hang_muc'             => $line['hang_muc'] ?? null,
                    'hang_muc_goc'         => $line['hang_muc_goc'] ?? null,
                    'chi_tiet'             => $line['chi_tiet'] ?? null,
                    'dvt'                  => $line['dvt'] ?? null,
                    'so_luong'             => $line['so_luong'] ?? 0,

                    'don_gia'              => $line['don_gia'] ?? 0,
                    'thanh_tien'           => $line['thanh_tien'] ?? 0,
                    'is_package'           => (bool) ($line['is_package'] ?? false),

                    'nguoi_tao'            => (string) (Auth::id() ?? ''),
                    'nguoi_cap_nhat'       => (string) (Auth::id() ?? ''),
                ]);
            }
        }
    }

    /**
     * Helper: tự động tính lại số tiền các đợt thanh toán dựa trên % và tổng HĐ
     * - Nếu FE chỉ nhập dot1_ty_le, dot2_ty_le, hệ thống sẽ tính dot1_so_tien, dot2_so_tien
     * - Nếu không có tổng HĐ (tong_sau_vat <= 0) thì bỏ qua, giữ nguyên dữ liệu cũ
     */
    protected function recalcPaymentFromPercent(HopDong $hopDong, array &$data): void
    {
        // Tổng HĐ: ưu tiên dữ liệu mới FE gửi, nếu không có thì dùng DB hiện tại
        $total = (int) ($data['tong_sau_vat'] ?? $hopDong->tong_sau_vat ?? 0);
        if ($total <= 0) {
            return;
        }

        // Lấy % đợt 1/2: ưu tiên giá trị FE gửi, fallback DB
        $tyLe1 = array_key_exists('dot1_ty_le', $data)
            ? (int) $data['dot1_ty_le']
            : (int) $hopDong->dot1_ty_le;

        $tyLe2 = array_key_exists('dot2_ty_le', $data)
            ? (int) $data['dot2_ty_le']
            : (int) $hopDong->dot2_ty_le;

        // Clamp về [0, 100] cho an toàn
        $tyLe1 = max(0, min(100, $tyLe1));
        $tyLe2 = max(0, min(100, $tyLe2));

        // Tính tiền Đợt 1
        $amount1 = (int) round($total * $tyLe1 / 100);

        // Đợt 2:
        // - Nếu FE có nhập % rõ ràng → tính theo %
        // - Nếu không → lấy phần còn lại
        if (array_key_exists('dot2_ty_le', $data)) {
            $amount2 = (int) round($total * $tyLe2 / 100);
        } else {
            $amount2 = $total - $amount1;
            $tyLe2   = $total > 0 ? (int) round($amount2 * 100 / $total) : 0;
        }

        $data['dot1_ty_le']   = $tyLe1;
        $data['dot1_so_tien'] = $amount1;
        $data['dot2_ty_le']   = $tyLe2;
        $data['dot2_so_tien'] = $amount2;
    }

    /**
     * Helper chuyển số → chữ (tiền VNĐ)
     * - Ở đây copy logic khái quát; nếu anh đã có helper riêng thì có thể thay thế.
     */
    protected function vnNumberToText(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }

        // Đơn giản: chia triệu / nghìn / đồng
        $units = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];
        $nums  = ['không','một','hai','ba','bốn','năm','sáu','bảy','tám','chín'];

        $result = '';
        $unit   = 0;

        while ($number > 0 && $unit < count($units)) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $chunkText = '';

                $tram = intdiv($chunk, 100);
                $du   = $chunk % 100;
                $chuc = intdiv($du, 10);
                $donv = $du % 10;

                if ($tram > 0) {
                    $chunkText .= $nums[$tram] . ' trăm';
                    if ($du > 0 && $du < 10) {
                        $chunkText .= ' lẻ';
                    }
                }

                if ($chuc > 1) {
                    $chunkText .= ' ' . $nums[$chuc] . ' mươi';
                    if ($donv === 1) {
                        $chunkText .= ' mốt';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $nums[$donv];
                    }
                } elseif ($chuc === 1) {
                    $chunkText .= ' mười';
                    if ($donv === 1) {
                        $chunkText .= ' một';
                    } elseif ($donv === 5) {
                        $chunkText .= ' lăm';
                    } elseif ($donv > 0) {
                        $chunkText .= ' ' . $nums[$donv];
                    }
                } elseif ($chuc === 0 && $donv > 0) {
                    if ($tram > 0) {
                        $chunkText .= ' lẻ';
                    }
                    if ($donv === 5 && $tram > 0) {
                        $chunkText .= ' lăm';
                    } else {
                        $chunkText .= ' ' . $nums[$donv];
                    }
                }

                $chunkText = trim($chunkText) . $units[$unit];
                $result = $chunkText . ($result ? ' ' . $result : '');
            }

            $number = intdiv($number, 1000);
            $unit++;
        }

        $result = trim($result);
        $result = mb_convert_case($result, MB_CASE_LOWER, 'UTF-8');
        $result = mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($result, 1, null, 'UTF-8');

        return $result . ' đồng chẵn./.';
    }
}
