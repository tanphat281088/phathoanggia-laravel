<?php

namespace App\Services\Payroll;

use App\Models\BangCongThang;
use App\Models\LuongProfile;
use App\Models\LuongThang;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * BangLuongService
 *
 * - Tính & upsert snapshot lương theo tháng (YYYY-MM).
 * - Tôn trọng locked=true (không ghi đè).
 * - Cho phép tính 1 user hoặc toàn bộ users.
 *
 * Quy ước:
 *  - Đơn vị tiền: VND (integer).
 *  - Tháng: chuỗi 'YYYY-MM'.
 *  - Công thức mặc định (có thể tùy chỉnh sau):
 *      luong_theo_cong = (luong_co_ban * he_so) * (so_ngay_cong / cong_chuan)
 *      bao_hiem_tinh_tren = (luong_co_ban * he_so)
 *      bhxh = round(bao_hiem_tinh_tren * pt_bhxh / 100)
 *      bhyt = round(bao_hiem_tinh_tren * pt_bhyt / 100)
 *      bhtn = round(bao_hiem_tinh_tren * pt_bhtn / 100)
 *      thuc_nhan = luong_theo_cong + phu_cap + thuong - phat - (bhxh+bhyt+bhtn) - khau_tru_khac - tam_ung
 *
 * - Nếu thiếu LuongProfile: dùng default an toàn.
 * - Nếu thiếu BangCongThang: cố gắng đọc 0 công; controller có thể "lazy-compute" bảng công trước khi gọi.
 */
class BangLuongService
{
    /** Default cấu hình khi user CHƯA có LuongProfile */
    private array $defaults = [
        'muc_luong_co_ban'     => 0,
        'cong_chuan'           => 26,
        'he_so'                => 1.00,
        'phu_cap_mac_dinh'     => 0,
        'pt_bhxh'              => 8.00,
        'pt_bhyt'              => 1.50,
        'pt_bhtn'              => 1.00,

        // ===== NEW (an toàn nếu cột chưa có sẽ fallback 0) =====
        'salary_mode'          => 'cham_cong', // 'khoan' | 'cham_cong'
        'apply_insurance'      => 1,           // 1=trừ BH, 0=không trừ (Cách B)
        'insurance_base_mode'  => 'prorate',   // 'base' | 'prorate' | 'none'
        'cong_chuan_override'  => 28,          // mặc định theo yêu cầu (28 ngày/tháng)

        // Phụ cấp tham khảo (giữ 0 nếu chưa dùng)
        'support_allowance'    => 0,
        'phone_allowance'      => 0,
        'meal_per_day'         => 0,
        'meal_extra_default'   => 0,
    ];

    /**
     * Tính lương cho 1 người hoặc toàn bộ.
     * - $userId = null  => tất cả users có dòng BangCongThang của tháng đó.
     * - Tôn trọng locked=true (bỏ qua khi cập nhật).
     */
    public function computeMonth(string $thang, ?int $userId = null): void
    {
        // Lấy danh sách user cần tính, ưu tiên theo bảng công tháng.
        $uids = $this->pickUserIdsForMonth($thang, $userId);
        if (empty($uids)) return;

        foreach ($uids as $uid) {
            $this->computeOne($thang, (int) $uid);
        }
    }

    /**
     * Tính & upsert snapshot cho 1 user + tháng (an toàn).
     * - Không ghi đè nếu locked=true (giữ nguyên hàng hiện có).
     */
    public function computeOne(string $thang, int $userId): void
    {
        // 1) Lấy snapshot công (BangCongThang) của tháng (có thể null)
        $bc = BangCongThang::query()
            ->ofUser($userId)
            ->month($thang)
            ->orderByDesc('computed_at')
            ->orderByDesc('updated_at')
            ->first();

        // 2) Lấy profile lương (hoặc default)
        $profile = LuongProfile::query()->ofUser($userId)->first();

        $cfg = [
            'luong_co_ban'        => (int)   ($profile?->muc_luong_co_ban    ?? $this->defaults['muc_luong_co_ban']),
            'cong_chuan'          => (int)   ($profile?->cong_chuan          ?? $this->defaults['cong_chuan']),
            'he_so'               => (float) ($profile?->he_so               ?? $this->defaults['he_so']),
            'phu_cap_def'         => (int)   ($profile?->phu_cap_mac_dinh    ?? $this->defaults['phu_cap_mac_dinh']),
            'pt_bhxh'             => (float) ($profile?->pt_bhxh             ?? $this->defaults['pt_bhxh']),
            'pt_bhyt'             => (float) ($profile?->pt_bhyt             ?? $this->defaults['pt_bhyt']),
            'pt_bhtn'             => (float) ($profile?->pt_bhtn             ?? $this->defaults['pt_bhtn']),

            'salary_mode'         => (string)($profile?->salary_mode         ?? $this->defaults['salary_mode']),
            'apply_insurance'     => (int)   ($profile?->apply_insurance     ?? $this->defaults['apply_insurance']),
            'insurance_base_mode' => (string)($profile?->insurance_base_mode ?? $this->defaults['insurance_base_mode']),
            'cong_chuan_override' => (int)   ($profile?->cong_chuan_override ?? $this->defaults['cong_chuan_override']),

            'support_allowance'   => (int)   ($profile?->support_allowance   ?? $this->defaults['support_allowance']),
            'phone_allowance'     => (int)   ($profile?->phone_allowance     ?? $this->defaults['phone_allowance']),
            'meal_per_day'        => (int)   ($profile?->meal_per_day        ?? $this->defaults['meal_per_day']),
            'meal_extra_default'  => (int)   ($profile?->meal_extra_default  ?? $this->defaults['meal_extra_default']),
        ];

        // 3) Chỉ số công (nếu chưa có bảng công => 0)
        $soNgayCong = $bc ? (float) $bc->so_ngay_cong : 0.0;
        // ⚠️ so_gio_cong đang lưu PHÚT (không phải giờ)
        $soGioCong  = $bc ? max(0, (int) $bc->so_gio_cong) : 0;

        // 4) Tính toán lương & BH

        // Base lương = MLCB * hệ số
        $baseXHeSo    = (int) round($cfg['luong_co_ban'] * $cfg['he_so']);

        // Công chuẩn hiệu lực (dùng cho báo cáo & ước tính theo ngày)
        $congChuanEff = max(1, (int) ($cfg['cong_chuan_override'] ?: $cfg['cong_chuan']));
        $prorateDay   = max(0, min(1, $congChuanEff > 0 ? ($soNgayCong / $congChuanEff) : 0));
        $dailyRate    = (int) round($congChuanEff > 0 ? ($baseXHeSo / $congChuanEff) : 0);

        // ----- Lương theo phút công (chỉ áp dụng cho salary_mode = 'cham_cong') -----
        // Chuẩn phút công/tháng: cong_chuan_override (hoặc cong_chuan) * 8h * 60'
        $stdMinutes = max(1, (int) ($congChuanEff * 8 * 60)); // ví dụ: 28 * 8 * 60 = 13440
        $actualMinutes = $soGioCong;                          // tổng phút công thực tế

        $otRatePerMin = 250;                                  // đơn giá tăng ca / phút
        $unitBasePerMin = (int) ($stdMinutes > 0 ? round($baseXHeSo / $stdMinutes) : 0);

        $baseMinutes = 0;   // số phút tính lương cơ bản
        $otMinutes   = 0;   // số phút tăng ca dùng để tính lương tăng ca
        $otAmount    = 0;   // tiền tăng ca
        $luongTheoCong = 0; // sẽ gán vào row->luong_theo_cong

        if ($cfg['salary_mode'] === 'khoan') {
            // Giữ logic lương khoán cũ: không phụ thuộc bảng công
            $luongTheoCong = $baseXHeSo;
            $baseMinutes   = $stdMinutes;
            $otMinutes     = 0;
            $otAmount      = 0;
        } else {
            // Lương chấm công theo PHÚT
            if ($actualMinutes >= $stdMinutes) {
                // ĐÃ ĐỦ chuẩn → nhận đủ lương cơ bản + lương tăng ca
                $baseMinutes   = $stdMinutes;
                $otMinutes     = $actualMinutes - $stdMinutes;
                $otAmount      = (int) ($otMinutes * $otRatePerMin);

                // Lương chuẩn = full baseXHeSo
                $luongTheoCong = $baseXHeSo + $otAmount;
            } else {
                // CHƯA ĐỦ chuẩn → lương theo phút thực tế, không có tăng ca
                $baseMinutes   = $actualMinutes;
                $otMinutes     = 0;
                $otAmount      = 0;

                $luongTheoCong = (int) ($unitBasePerMin * $actualMinutes);
            }
        }

        // ----- Bảo hiểm: dùng base theo phút công (hoặc full base tuỳ cấu hình) -----
        if (!$cfg['apply_insurance'] || $cfg['insurance_base_mode'] === 'none') {
            $bhBase = 0;
            $bhxh = $bhyt = $bhtn = 0;
        } else {
            // Tỷ lệ phút so với chuẩn (0..1)
            $ratioMinutes = min(1.0, $stdMinutes > 0 ? ($actualMinutes / $stdMinutes) : 0.0);

            if ($cfg['insurance_base_mode'] === 'base') {
                // BH luôn tính trên full base
                $bhBase = $baseXHeSo;
            } else {
                // 'prorate' theo phút công thực tế
                $bhBase = (int) round($baseXHeSo * $ratioMinutes);
            }

            $bhxh = (int) round($bhBase * $cfg['pt_bhxh'] / 100);
            $bhyt = (int) round($bhBase * $cfg['pt_bhyt'] / 100);
            $bhtn = (int) round($bhBase * $cfg['pt_bhtn'] / 100);
        }

        // (tuỳ chọn) khoản ăn trưa & phụ cấp cố định: hiện tại KHÔNG cộng thẳng vào snapshot để không ghi đè thủ công.
        // Nếu muốn tự động, có thể tính:
        // $allowFixed = $cfg['phu_cap_def'] + $cfg['support_allowance'] + $cfg['phone_allowance'] + $cfg['meal_extra_default'];
        // $mealAmount = $cfg['meal_per_day'] * (int)$soNgayCong;

        // 5) Upsert an toàn (bỏ qua dòng locked)
        DB::transaction(function () use (
            $thang,
            $userId,
            $cfg,
            $soNgayCong,
            $soGioCong,
            $luongTheoCong,
            $bhxh,
            $bhyt,
            $bhtn,
            $congChuanEff,
            $dailyRate,
            $prorateDay,
            $bhBase,
            $baseXHeSo,
            $stdMinutes,
            $actualMinutes,
            $baseMinutes,
            $otMinutes,
            $unitBasePerMin,
            $otRatePerMin,
            $otAmount
        ) {
            /** @var LuongThang|null $row */
            $row = LuongThang::query()
                ->ofUser($userId)
                ->month($thang)
                ->lockForUpdate()
                ->first();

            if ($row && $row->locked) {
                // Tôn trọng locked: không ghi đè
                return;
            }

            // Nếu chưa có, tạo mới với mặc định phụ cấp từ profile
            if (!$row) {
                $row = new LuongThang();
                $row->user_id  = $userId;
                $row->thang    = $thang;

                // Phụ cấp mặc định (KHÔNG cộng meal theo ngày để tránh ghi đè thủ công):
                $row->phu_cap  = (int) ($cfg['phu_cap_def'] + $cfg['support_allowance'] + $cfg['phone_allowance'] + $cfg['meal_extra_default']);

                $row->thuong   = 0;
                $row->phat     = 0;
                $row->tam_ung  = 0;
                $row->khau_tru_khac = 0;
            }

            // Cập nhật snapshot cơ bản
            $row->luong_co_ban    = (int) $cfg['luong_co_ban'];
            $row->cong_chuan      = (int) $congChuanEff;       // dùng công chuẩn hiệu lực (override nếu có)
            $row->he_so           = (float) $cfg['he_so'];

            $row->so_ngay_cong    = (float) $soNgayCong;
            $row->so_gio_cong     = (int)  $soGioCong;

            $row->luong_theo_cong = (int)  $luongTheoCong;
            $row->bhxh            = (int)  $bhxh;
            $row->bhyt            = (int)  $bhyt;
            $row->bhtn            = (int)  $bhtn;

            // (optional) lưu breakdown vào ghi_chu để audit / hiển thị
            $note = [
                // Giữ các key cũ để FE/Admin controller đọc được
                'mode'        => $cfg['salary_mode'],
                'base'        => $baseXHeSo,
                'daily_rate'  => $dailyRate,
                'cong_chuan'  => $congChuanEff,
                'prorate'     => $prorateDay,
                'bh_base'     => $bhBase,

                // Thông tin mới theo PHÚT công
                'std_minutes'      => $stdMinutes,
                'actual_minutes'   => $actualMinutes,
                'base_minutes'     => $baseMinutes,
                'ot_minutes'       => $otMinutes,
                'unit_base_min'    => $unitBasePerMin,
                'ot_rate_per_min'  => $otRatePerMin,
                'ot_amount'        => $otAmount,
            ];
            $row->ghi_chu = json_encode($note, JSON_UNESCAPED_UNICODE);
            // ===== TÍNH THỰC NHẬN (NET PAY) MỘT CÁCH AN TOÀN =====
            // Ép tất cả về integer để tránh null / kiểu lạ
            $luongCong   = (int) ($row->luong_theo_cong ?? 0);
            $phuCap      = (int) ($row->phu_cap ?? 0);
            $thuong      = (int) ($row->thuong ?? 0);
            $phat        = (int) ($row->phat ?? 0);
            $bhxhVal     = (int) ($row->bhxh ?? 0);
            $bhytVal     = (int) ($row->bhyt ?? 0);
            $bhtnVal     = (int) ($row->bhtn ?? 0);
            $khauTruKhac = (int) ($row->khau_tru_khac ?? 0);
            $tamUngVal   = (int) ($row->tam_ung ?? 0);

            // P = lương theo công + phụ cấp + thưởng - phạt
            $tongBH       = $bhxhVal + $bhytVal + $bhtnVal;
            $thuNhapTruoc = $luongCong + $phuCap + $thuong - $phat;

            // U = max(0, P - Q - R - T)
            $netPay = (int) max(0, $thuNhapTruoc - $tongBH - $khauTruKhac - $tamUngVal);
            $row->thuc_nhan = $netPay;

            // DEBUG: log 1 dòng để soi nhanh nếu cần
            \Log::info('Payroll::computeOne snapshot', [
                'user_id'        => $userId,
                'thang'          => $thang,
                'luong_cong'     => $luongCong,
                'phu_cap'        => $phuCap,
                'thuong'         => $thuong,
                'phat'           => $phat,
                'bhxh'           => $bhxhVal,
                'bhyt'           => $bhytVal,
                'bhtn'           => $bhtnVal,
                'khau_tru_khac'  => $khauTruKhac,
                'tam_ung'        => $tamUngVal,
                'P_gross'        => $thuNhapTruoc,
                'Q_insurance'    => $tongBH,
                'U_net'          => $netPay,
            ]);

            $row->computed_at = now();
            $row->save();

        });
    }

    /**
     * Chọn danh sách user cần tính cho 1 tháng.
     * - Ưu tiên dựa trên bảng công tháng (ai có công/tháng đó).
     * - Nếu truyền userId => chỉ tính người đó, kể cả khi chưa có bảng công (sẽ ra 0 công).
     */
    private function pickUserIdsForMonth(string $thang, ?int $userId = null): array
    {
        if ($userId) return [(int) $userId];

        // Users ACTIVE (nếu có cột 'trang_thai'); nếu không có cột thì lấy tất cả users
        $active = User::query()
            ->when(\Schema::hasColumn('users', 'trang_thai'), fn ($q) => $q->where('trang_thai', 1))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        // Users có bảng công trong tháng
        $timesheet = \App\Models\BangCongThang::query()
            ->where('thang', $thang)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        // Hợp nhất: ACTIVE ∪ CÓ BẢNG CÔNG
        return array_values(array_unique(array_merge($active, $timesheet)));
    }
}
