<?php

namespace App\Modules\NhanSu\Payroll;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * LuongProfileController
 * - GET  /nhan-su/luong-profile?user_id=
 * - POST /nhan-su/luong-profile/upsert
 * - GET  /nhan-su/luong/preview?user_id=&thang=YYYY-MM
 *
 * Lưu ý:
 * - Trả JSON theo CustomResponse nếu có.
 * - Preview chỉ tính toán trên bộ nhớ, KHÔNG ghi DB snapshot.
 */
class LuongProfileController extends Controller
{
    // ===== Helper response =====
    private function ok($data = [], string $code = 'OK', int $status = 200) {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }
        return response()->json(['success' => true, 'data' => $data, 'code' => $code], $status);
    }
    private function fail($data = [], string $code = 'ERROR', int $status = 400) {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }
        return response()->json(['success' => false, 'data' => $data, 'code' => $code], $status);
    }

    /**
     * GET /nhan-su/luong-profile?user_id=
     * - Lấy hồ sơ lương hiện tại của 1 user (nếu chưa có -> trả default).
     */
    public function get(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
        ]);
        if ($v->fails()) return $this->fail($v->errors(), 'VALIDATION_ERROR', 422);

        $uid = (int) $request->input('user_id');

        $p = DB::table('luong_profiles')->where('user_id', $uid)->first();
        if (!$p) {
            // Default an toàn
            $p = (object)[
                'user_id'             => $uid,
                'salary_mode'         => 'cham_cong', // khoan|cham_cong
                'muc_luong_co_ban'    => 0,
                'he_so'               => 1.00,
                'cong_chuan'          => 26,
                'cong_chuan_override' => 28,          // mặc định yêu cầu của bạn
                'support_allowance'   => 0,
                'phone_allowance'     => 0,
                'meal_per_day'        => 0,
                'meal_extra_default'  => 0,
                'apply_insurance'     => 1,
                'insurance_base_mode' => 'prorate',   // base|prorate|none
                'pt_bhxh'             => 8.00,
                'pt_bhyt'             => 1.50,
                'pt_bhtn'             => 1.00,
                'note'                => null,
                'effective_from'      => null,
                'effective_to'        => null,
            ];
        }

        return $this->ok(['profile' => $p], 'PAYROLL_PROFILE');
    }

    /**
     * POST /nhan-su/luong-profile/upsert
     * body: see fields below
     */
    public function upsert(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id'             => ['required', 'integer', 'min:1'],
            'salary_mode'         => ['nullable', 'in:khoan,cham_cong'],
            'muc_luong_co_ban'    => ['nullable', 'integer', 'min:0'],
            'he_so'               => ['nullable', 'numeric', 'min:0'],
            'cong_chuan'          => ['nullable', 'integer', 'min:1'],
            'cong_chuan_override' => ['nullable', 'integer', 'min:1'],
            'support_allowance'   => ['nullable', 'integer', 'min:0'],
            'phone_allowance'     => ['nullable', 'integer', 'min:0'],
            'meal_per_day'        => ['nullable', 'integer', 'min:0'],
            'meal_extra_default'  => ['nullable', 'integer', 'min:0'],
            'apply_insurance'     => ['nullable', 'boolean'],
            'insurance_base_mode' => ['nullable', 'in:base,prorate,none'],
            'pt_bhxh'             => ['nullable', 'numeric', 'min:0'],
            'pt_bhtn'             => ['nullable', 'numeric', 'min:0'],
            'pt_bhyt'             => ['nullable', 'numeric', 'min:0'],
            'note'                => ['nullable', 'string'],
            'effective_from'      => ['nullable', 'date'],
            'effective_to'        => ['nullable', 'date'],
        ]);
        if ($v->fails()) return $this->fail($v->errors(), 'VALIDATION_ERROR', 422);

        $uid = (int) $request->input('user_id');

        $payload = $v->validated() + [
            'salary_mode'         => $request->input('salary_mode', 'cham_cong'),
            'muc_luong_co_ban'    => (int) $request->input('muc_luong_co_ban', 0),
            'he_so'               => (float) $request->input('he_so', 1.00),
            'cong_chuan'          => (int) $request->input('cong_chuan', 26),
            'cong_chuan_override' => $request->input('cong_chuan_override'),
            'support_allowance'   => (int) $request->input('support_allowance', 0),
            'phone_allowance'     => (int) $request->input('phone_allowance', 0),
            'meal_per_day'        => (int) $request->input('meal_per_day', 0),
            'meal_extra_default'  => (int) $request->input('meal_extra_default', 0),
            'apply_insurance'     => (int) $request->input('apply_insurance', 1),
            'insurance_base_mode' => $request->input('insurance_base_mode', 'prorate'),
            'pt_bhxh'             => (float) $request->input('pt_bhxh', 8.00),
            'pt_bhtn'             => (float) $request->input('pt_bhtn', 1.00),
            'pt_bhyt'             => (float) $request->input('pt_bhyt', 1.50),
            'note'                => $request->input('note'),
            'effective_from'      => $request->input('effective_from'),
            'effective_to'        => $request->input('effective_to'),
            'updated_at'          => now(),
        ];

        // ===== SANITIZE: loại bỏ null và điền default an toàn =====
        $payload = array_filter($payload, static fn($v) => !is_null($v)); // bỏ hết null

        // Defaults bắt buộc
        $payload['salary_mode']         = $payload['salary_mode']         ?? 'cham_cong';
        $payload['muc_luong_co_ban']    = isset($payload['muc_luong_co_ban'])    ? (int)$payload['muc_luong_co_ban']    : 0;
        $payload['he_so']               = isset($payload['he_so'])               ? (float)$payload['he_so']               : 1.0;
        $payload['cong_chuan']          = isset($payload['cong_chuan'])          ? (int)$payload['cong_chuan']            : 26;
        $payload['apply_insurance']     = isset($payload['apply_insurance'])     ? (int)$payload['apply_insurance']       : 1;
        $payload['insurance_base_mode'] = $payload['insurance_base_mode'] ?? 'prorate';

        // Phần trăm BH
        $payload['pt_bhxh'] = isset($payload['pt_bhxh']) ? (float)$payload['pt_bhxh'] : 8.0;
        $payload['pt_bhtn'] = isset($payload['pt_bhtn']) ? (float)$payload['pt_bhtn'] : 1.0;
        $payload['pt_bhyt'] = isset($payload['pt_bhyt']) ? (float)$payload['pt_bhyt'] : 1.5;

        // Phụ cấp (để 0 nếu thiếu)
        $payload['support_allowance']   = (int)($payload['support_allowance']   ?? 0);
        $payload['phone_allowance']     = (int)($payload['phone_allowance']     ?? 0);
        $payload['meal_per_day']        = (int)($payload['meal_per_day']        ?? 0);
        $payload['meal_extra_default']  = (int)($payload['meal_extra_default']  ?? 0);

        // nếu chưa có -> tạo
        if (!DB::table('luong_profiles')->where('user_id', $uid)->exists()) {
            $payload['user_id']   = $uid;
            $payload['created_at']= now();
        }

        DB::table('luong_profiles')->updateOrInsert(['user_id' => $uid], $payload);

        return $this->ok(['user_id' => $uid, 'profile' => $payload], 'UPSERT_OK');
    }

    /**
     * GET /nhan-su/luong/preview?user_id=&thang=YYYY-MM
     * - Tính thử theo hồ sơ hiện tại + bảng công tháng.
     * - KHÔNG ghi DB snapshot.
     */
    public function preview(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        if ($v->fails()) return $this->fail($v->errors(), 'VALIDATION_ERROR', 422);

        $uid   = (int) $request->input('user_id');
        $thang = $request->input('thang') ?: now()->format('Y-m');

        // Hồ sơ
        $p = DB::table('luong_profiles')->where('user_id', $uid)->first();
        if (!$p) {
            $p = (object)[
                'salary_mode'         => 'cham_cong',
                'muc_luong_co_ban'    => 0,
                'he_so'               => 1.00,
                'cong_chuan'          => 26,
                'cong_chuan_override' => 28,
                'support_allowance'   => 0,
                'phone_allowance'     => 0,
                'meal_per_day'        => 0,
                'meal_extra_default'  => 0,
                'apply_insurance'     => 1,
                'insurance_base_mode' => 'prorate',
                'pt_bhxh'             => 8.00,
                'pt_bhtn'             => 1.00,
                'pt_bhyt'             => 1.50,
            ];
        }

        // Bảng công tháng
        $bc = DB::table('bang_cong_thangs')
            ->where('user_id', $uid)
            ->where('thang', $thang)
            ->first();

        $so_ngay_cong = (float) ($bc->so_ngay_cong ?? 0);
        // ⚠️ so_gio_cong đang lưu PHÚT (không phải giờ)
        $so_gio_cong  = (int)   ($bc->so_gio_cong  ?? 0);

        // ===== TÍNH TOÁN THEO PHÚT CÔNG =====

        // Lương cơ bản đã nhân hệ số
        $base = (int) round(($p->muc_luong_co_ban ?? 0) * ($p->he_so ?? 1.0));

        // Công chuẩn hiệu lực (dùng cho báo cáo, và để tính phút chuẩn)
        $congChuan = (int) ($p->cong_chuan_override ?? $p->cong_chuan ?? 26);
        $congChuan = max(1, $congChuan);

        // Chuẩn phút công/tháng
        $stdMinutes    = max(1, $congChuan * 8 * 60);   // ví dụ 28 * 8 * 60 = 13440
        $actualMinutes = max(0, $so_gio_cong);          // tổng phút công thực tế

        // Đơn giá lương cơ bản / phút
        $unit_base_min = $stdMinutes > 0 ? (int) round($base / $stdMinutes) : 0;

        $salary_mode   = $p->salary_mode ?? 'cham_cong';
        $ot_rate_per_min = 250;

        $base_minutes = 0;
        $ot_minutes   = 0;
        $ot_amount    = 0;
        $luong_theo_cong = 0;

        if ($salary_mode === 'khoan') {
            // Lương khoán: nhận đủ base, không phụ thuộc phút công
            $base_minutes    = $stdMinutes;
            $ot_minutes      = 0;
            $ot_amount       = 0;
            $luong_theo_cong = $base;
        } else {
            // Lương chấm công theo PHÚT
            if ($actualMinutes >= $stdMinutes) {
                // ĐỦ chuẩn: lương cơ bản + tăng ca
                $base_minutes    = $stdMinutes;
                $ot_minutes      = $actualMinutes - $stdMinutes;
                $ot_amount       = (int) ($ot_minutes * $ot_rate_per_min);
                $luong_theo_cong = $base + $ot_amount;
            } else {
                // CHƯA ĐỦ chuẩn: lương = đơn giá/phút * phút thực tế
                $base_minutes    = $actualMinutes;
                $ot_minutes      = 0;
                $ot_amount       = 0;
                $luong_theo_cong = (int) ($unit_base_min * $actualMinutes);
            }
        }

        // Phụ cấp cố định + cơm (preview: chỉ để tham khảo, không ghi DB)
        $allow_fixed  = (int) ($p->support_allowance ?? 0) + (int) ($p->phone_allowance ?? 0);
        $meal_days    = (int) $so_ngay_cong;
        $meal_amount  = (int) ($p->meal_per_day ?? 0) * $meal_days + (int) ($p->meal_extra_default ?? 0);

        // ===== BẢO HIỂM THEO PHÚT CÔNG =====
        $applyIns = (int) ($p->apply_insurance ?? 1);
        $bhxh = $bhyt = $bhtn = 0;
        $bh_base = 0;

        if ($applyIns && (($p->insurance_base_mode ?? 'prorate') !== 'none')) {
            $ratioMinutes = $stdMinutes > 0 ? min(1.0, $actualMinutes / $stdMinutes) : 0.0;

            if (($p->insurance_base_mode ?? 'prorate') === 'base') {
                $bh_base = $base;
            } else {
                $bh_base = (int) round($base * $ratioMinutes);
            }

            $bhxh = (int) round($bh_base * (float)($p->pt_bhxh ?? 0) / 100);
            $bhyt = (int) round($bh_base * (float)($p->pt_bhyt ?? 0) / 100);
            $bhtn = (int) round($bh_base * (float)($p->pt_bhtn ?? 0) / 100);
        }

        // ===== P/Q/R/T/U CHO PREVIEW =====
        // Ở preview, coi phụ cấp cố định + cơm là phần cộng (phu_cap), R/T mặc định = 0
        $P = (int) ($luong_theo_cong + $allow_fixed + $meal_amount); // Gross theo phút + phụ cấp
        $Q = (int) ($bhxh + $bhyt + $bhtn);                           // BH
        $R = 0;                                                       // khấu trừ khác (preview: 0)
        $T = 0;                                                       // tạm ứng (preview: 0)
        $U = max(0, $P - $Q - $R - $T);                               // Net preview

        return $this->ok([
            'thang'   => $thang,
            'user_id' => $uid,
            'metrics' => [
                // Cấu hình & công
                'base'            => $base,
                'cong_chuan'      => $congChuan,
                'so_ngay_cong'    => $so_ngay_cong,
                'so_gio_cong'     => $so_gio_cong,
                'std_minutes'     => $stdMinutes,
                'actual_minutes'  => $actualMinutes,
                'base_minutes'    => $base_minutes,
                'ot_minutes'      => $ot_minutes,

                // Đơn giá & tăng ca
                'salary_mode'     => $salary_mode,
                'unit_base_min'   => $unit_base_min,
                'ot_rate_per_min' => $ot_rate_per_min,
                'ot_amount'       => $ot_amount,

                // Lương theo công (phút)
                'luong_theo_cong' => $luong_theo_cong,

                // Phụ cấp & ăn trưa
                'allow_fixed'     => $allow_fixed,
                'meal_amount'     => $meal_amount,

                // BH & tổng hợp
                'bh_base'         => $bh_base,
                'bhxh'            => $bhxh,
                'bhyt'            => $bhyt,
                'bhtn'            => $bhtn,

                // P/Q/R/T/U (preview)
                'P_gross'         => $P,
                'Q_insurance'     => $Q,
                'R_deduct_other'  => $R,
                'T_advance'       => $T,
                'U_net'           => $U,
            ],
        ], 'PAYROLL_PREVIEW');
    }
}
