<?php

namespace App\Modules\NhanSu\Payroll;

use App\Http\Controllers\Controller as BaseController;
use App\Models\LuongThang;
use App\Services\Payroll\BangLuongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BangLuongAdminController extends BaseController
{
    /**
     * GET /nhan-su/bang-luong?user_id=&thang=YYYY-MM
     * - Xem bảng lương 1 người (quyền quản lý).
     */
public function adminShow(Request $request)
{
    $v = Validator::make($request->all(), [
        'user_id' => ['required', 'integer', 'min:1'],
        'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
    ]);
    if ($v->fails()) {
        return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
    }

    $userId = (int) $request->input('user_id');
    $thang  = $request->input('thang') ?: \App\Services\Timesheet\BangCongService::cycleLabelForDate(now());

    try {
        // Lấy snapshot lương hiện tại
        $row = LuongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser($userId)
            ->month($thang)
            ->first();

        // Lazy compute nếu chưa có
        if (!$row) {
            try {
                /** @var \App\Services\Timesheet\BangCongService $ts */
                $ts = app(\App\Services\Timesheet\BangCongService::class);
                $ts->computeMonth($thang, $userId);

                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                $svc->computeMonth($thang, $userId);

                $row = LuongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser($userId)
                    ->month($thang)
                    ->first();

                \Log::info('Payroll lazy recompute ADMIN done', [
                    'uid' => $userId,
                    'thang' => $thang,
                    'found' => (bool) $row,
                ]);
            } catch (\Throwable $e) {
                \Log::error('Payroll lazy recompute ADMIN failed', [
                    'uid'   => $userId,
                    'thang' => $thang,
                    'err'   => $e->getMessage(),
                ]);
            }
        }

        // ⚠️ DEBUG: log trước khi trả về
        \Log::info('Payroll adminShow BEFORE toApi', [
            'uid'      => $userId,
            'thang'    => $thang,
            'has_row'  => (bool) $row,
            'row_id'   => $row?->id,
        ]);

        $item = $row ? $this->toApi($row) : null;

        \Log::info('Payroll adminShow OK', [
            'uid'        => $userId,
            'thang'      => $thang,
            'has_item'   => (bool) $item,
            'locked'     => $row?->locked,
            'computed_at'=> $row?->computed_at,
        ]);

        return $this->success([
            'user_id' => $userId,
            'thang'   => $thang,
            'item'    => $item,
        ], 'ADMIN_PAYROLL');
    } catch (\Throwable $e) {
        \Log::error('Payroll adminShow ERROR', [
            'uid'   => $userId,
            'thang' => $thang,
            'err'   => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $msg = config('app.debug') ? $e->getMessage() : 'Không thể lấy bảng lương.';
        return $this->failed(['message' => $msg], 'ADMIN_PAYROLL_ERROR', 500);
    }
}


    /**
     * GET /nhan-su/bang-luong/list?thang=YYYY-MM&page=1&per_page=50
     * - Danh sách lương toàn công ty trong 1 tháng (quyền quản lý).
     */
    public function adminList(Request $request)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
            'page'    => ['nullable', 'integer', 'min:1'],
            'per_page'=> ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang   = $request->input('thang') ?: now()->format('Y-m');
        $perPage = (int) ($request->input('per_page', 50));
        $page    = (int) ($request->input('page', 1));

        // ✅ 1) Lazy compute BẢNG CÔNG trước (để Payroll có dữ liệu đọc)
        try {
            /** @var \App\Services\Timesheet\BangCongService $ts */
            $ts = app(\App\Services\Timesheet\BangCongService::class);
            \Log::info('adminList: Timesheet lazy compute start', ['thang' => $thang]);
            $ts->computeMonth($thang, null);
        } catch (\Throwable $e) {
            \Log::warning('adminList: Timesheet lazy compute error', ['thang' => $thang, 'err' => $e->getMessage()]);
        }

        // ✅ 2) Nếu tháng chưa có snapshot lương → lazy compute Payroll
        if (!\App\Models\LuongThang::query()->where('thang', $thang)->exists()) {
            try {
                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                \Log::info('adminList: Payroll lazy compute start', ['thang' => $thang]);
                $svc->computeMonth($thang, null);
            } catch (\Throwable $e) {
                \Log::warning('adminList: Payroll lazy compute error', ['thang' => $thang, 'err' => $e->getMessage()]);
            }
        }

        $q = LuongThang::query()
            ->with(['user:id,name,email'])
            ->where('thang', $thang)
            ->orderByDesc('user_id');

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        // HOTFIX: map an toàn để không nổ 500
        $raw = $paginator->items();
        $items = array_map(function ($r) {
            $name = null;
            if (isset($r->user) && $r->user) {
                $name = $r->user->name ?? $r->user->email ?? null;
            }

            // Decode ghi_chu (có thể là json string hoặc array)
            $note = null;
            if (!empty($r->ghi_chu)) {
                if (is_string($r->ghi_chu)) {
                    try { $note = json_decode($r->ghi_chu, true, 512, JSON_THROW_ON_ERROR); }
                    catch (\Throwable $e) { $note = null; }
                } elseif (is_array($r->ghi_chu) || $r->ghi_chu instanceof \JsonSerializable) {
                    $note = (array) $r->ghi_chu;
                }
            }

            // Các key cũ
            $mode        = $note['mode']        ?? null;
            $base        = isset($note['base']) ? (int)$note['base'] : null;
            $daily_rate  = isset($note['daily_rate']) ? (int)$note['daily_rate'] : null;
            $cong_eff    = isset($note['cong_chuan']) ? (int)$note['cong_chuan'] : (int)$r->cong_chuan;
            $bh_base     = isset($note['bh_base']) ? (int)$note['bh_base'] : null;

            // Các key mới theo phút công (nếu có)
            $stdMinutes     = isset($note['std_minutes'])     ? (int)$note['std_minutes']     : null;
            $actualMinutes  = isset($note['actual_minutes'])  ? (int)$note['actual_minutes']  : null;
            $baseMinutes    = isset($note['base_minutes'])    ? (int)$note['base_minutes']    : null;
            $otMinutes      = isset($note['ot_minutes'])      ? (int)$note['ot_minutes']      : null;
            $unitBaseMin    = isset($note['unit_base_min'])   ? (int)$note['unit_base_min']   : null;
            $otRatePerMin   = isset($note['ot_rate_per_min']) ? (int)$note['ot_rate_per_min'] : null;
            $otAmount       = isset($note['ot_amount'])       ? (int)$note['ot_amount']       : null;

        // P/Q/R/T/U theo quy ước Excel: U = P − Q − R − T
        $gross = (int)$r->luong_theo_cong + (int)$r->phu_cap + (int)$r->thuong - (int)$r->phat; // P
        $qIns  = (int)$r->bhxh + (int)$r->bhyt + (int)$r->bhtn;                                 // Q
        $rDed  = (int)$r->khau_tru_khac;                                                        // R
        $tAdv  = (int)$r->tam_ung;                                                              // T
        // U = max(0, P - Q - R - T) — tính lại NET để không phụ thuộc snapshot cũ
        $net   = (int) max(0, $gross - $qIns - $rDed - $tAdv);


            return [
                'id'              => (int) $r->id,
                'user_id'         => (int) $r->user_id,
                'user_name'       => $name,
                'thang'           => (string) $r->thang,

                'luong_co_ban'    => (int) $r->luong_co_ban,
                'cong_chuan'      => $cong_eff,
                'he_so'           => (float) $r->he_so,
                'so_ngay_cong'    => (float) $r->so_ngay_cong,
                'so_gio_cong'     => (int)  $r->so_gio_cong,

                'luong_theo_cong' => (int)  $r->luong_theo_cong,
                'phu_cap'         => (int)  $r->phu_cap,
                'thuong'          => (int)  $r->thuong,
                'phat'            => (int)  $r->phat,

                'bhxh'            => (int)  $r->bhxh,
                'bhyt'            => (int)  $r->bhyt,
                'bhtn'            => (int)  $r->bhtn,
                'khau_tru_khac'   => $rDed,
                'tam_ung'         => $tAdv,
                     'thuc_nhan'       => $net,

                'P_gross'         => $gross,
                'Q_insurance'     => $qIns,
                'R_deduct_other'  => $rDed,
                'T_advance'       => $tAdv,
                'U_net'           => $net,

                // metrics phụ để hiển thị trong modal/FE
                'metrics' => [
                    // cũ
                    'mode'           => $mode,
                    'base'           => $base,
                    'daily_rate'     => $daily_rate,
                    'bh_base'        => $bh_base,
                    // mới theo phút công
                    'std_minutes'    => $stdMinutes,
                    'actual_minutes' => $actualMinutes,
                    'base_minutes'   => $baseMinutes,
                    'ot_minutes'     => $otMinutes,
                    'unit_base_min'  => $unitBaseMin,
                    'ot_rate_per_min'=> $otRatePerMin,
                    'ot_amount'      => $otAmount,
                ],

                'locked'          => (bool) $r->locked,
                'computed_at'     => $r->computed_at ? $r->computed_at->toDateTimeString() : null,
                'created_at'      => $r->created_at ? $r->created_at->toDateTimeString() : null,
                'updated_at'      => $r->updated_at ? $r->updated_at->toDateTimeString() : null,

                // giữ nguyên ghi_chu (raw) để debug nếu cần
                'ghi_chu'         => $r->ghi_chu,
            ];
        }, $raw);

        return $this->success([
            'thang'      => $thang,
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'items' => $items,
        ], 'ADMIN_PAYROLL_LIST');
    }

    /**
     * POST /nhan-su/bang-luong/recompute?thang=YYYY-MM&user_id=
     * - Tính lại 1 người hoặc toàn bộ (bỏ qua dòng locked).
     */
    public function recompute(Request $request, BangLuongService $svc)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang  = $request->input('thang') ?: now()->format('Y-m');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        try {
            // ✅ Recompute bảng công trước
            /** @var \App\Services\Timesheet\BangCongService $ts */
            $ts = app(\App\Services\Timesheet\BangCongService::class);
            $ts->computeMonth($thang, $userId);

            // ✅ Sau đó Payroll
            $svc->computeMonth($thang, $userId);

            return $this->success([
                'thang'   => $thang,
                'user_id' => $userId,
                'notice'  => 'Đã tổng hợp bảng lương.',
            ], 'RECOMPUTED_PAYROLL_OK');
        } catch (\Throwable $e) {
            \Log::error('Payroll recompute failed', [
                'thang'   => $thang, 'user_id' => $userId,
                'err'     => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $msg = config('app.debug') ? $e->getMessage() : 'Không thể tổng hợp bảng lương.';
            return $this->failed(['message' => $msg], 'RECOMPUTE_PAYROLL_FAILED', 500);
        }
    }

    // ===== Helpers =====

private function toApi(LuongThang $r): array
{
    // Giải mã ghi_chu (có thể là JSON string)
    $note = null;
    if (!empty($r->ghi_chu)) {
        if (is_string($r->ghi_chu)) {
            try {
                $note = json_decode($r->ghi_chu, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                \Log::warning('Payroll toApi: decode ghi_chu failed', [
                    'id'   => $r->id,
                    'err'  => $e->getMessage(),
                ]);
                $note = null;
            }
        } elseif (is_array($r->ghi_chu) || $r->ghi_chu instanceof \JsonSerializable) {
            $note = (array) $r->ghi_chu;
        }
    }

    // các key cũ
    $mode       = $note['mode']        ?? null;
    $base       = isset($note['base']) ? (int)$note['base'] : null;
    $daily_rate = isset($note['daily_rate']) ? (int)$note['daily_rate'] : null;
    $cong_eff   = isset($note['cong_chuan']) ? (int)$note['cong_chuan'] : (int)$r->cong_chuan;
    $bh_base    = isset($note['bh_base']) ? (int)$note['bh_base'] : null;

    // các key mới theo phút công
    $stdMinutes     = isset($note['std_minutes'])     ? (int)$note['std_minutes']     : null;
    $actualMinutes  = isset($note['actual_minutes'])  ? (int)$note['actual_minutes']  : null;
    $baseMinutes    = isset($note['base_minutes'])    ? (int)$note['base_minutes']    : null;
    $otMinutes      = isset($note['ot_minutes'])      ? (int)$note['ot_minutes']      : null;
    $unitBaseMin    = isset($note['unit_base_min'])   ? (int)$note['unit_base_min']   : null;
    $otRatePerMin   = isset($note['ot_rate_per_min']) ? (int)$note['ot_rate_per_min'] : null;
    $otAmount       = isset($note['ot_amount'])       ? (int)$note['ot_amount']       : null;

     // P/Q/R/T/U
    $gross = (int)$r->luong_theo_cong + (int)$r->phu_cap + (int)$r->thuong - (int)$r->phat; // P
    $qIns  = (int)$r->bhxh + (int)$r->bhyt + (int)$r->bhtn;                                 // Q
    $rDed  = (int)$r->khau_tru_khac;                                                        // R
    $tAdv  = (int)$r->tam_ung;                                                              // T
    // U = max(0, P - Q - R - T)
    $net   = (int) max(0, $gross - $qIns - $rDed - $tAdv);


    return [
        'id'              => (int)$r->id,
        'user_id'         => (int)$r->user_id,
        'user_name'       => $r->relationLoaded('user') && $r->user ? ($r->user->name ?? $r->user->email) : null,
        'thang'           => (string)$r->thang,

        'luong_co_ban'    => (int)$r->luong_co_ban,
        'cong_chuan'      => $cong_eff,
        'he_so'           => (float)$r->he_so,
        'so_ngay_cong'    => (float)$r->so_ngay_cong,
        // ⚠️ chắc chắn dùng đúng property, không có typo
        'so_gio_cong'     => (int)$r->so_gio_cong,

        'luong_theo_cong' => (int)$r->luong_theo_cong,
        'phu_cap'         => (int)$r->phu_cap,
        'thuong'          => (int)$r->thuong,
        'phat'            => (int)$r->phat,

        'bhxh'            => (int)$r->bhxh,
        'bhyt'            => (int)$r->bhyt,
        'bhtn'            => (int)$r->bhtn,
        'khau_tru_khac'   => $rDed,
        'tam_ung'         => $tAdv,
           'thuc_nhan'       => $net,

        // P/Q/R/T/U
        'P_gross'         => $gross,
        'Q_insurance'     => $qIns,
        'R_deduct_other'  => $rDed,
        'T_advance'       => $tAdv,
        'U_net'           => $net,

        // metrics từ service (nếu có)
        'metrics' => [
            'mode'           => $mode,
            'base'           => $base,
            'daily_rate'     => $daily_rate,
            'bh_base'        => $bh_base,
            'std_minutes'    => $stdMinutes,
            'actual_minutes' => $actualMinutes,
            'base_minutes'   => $baseMinutes,
            'ot_minutes'     => $otMinutes,
            'unit_base_min'  => $unitBaseMin,
            'ot_rate_per_min'=> $otRatePerMin,
            'ot_amount'      => $otAmount,
        ],

        'locked'          => (bool)$r->locked,
        'computed_at'     => $r->computed_at?->toDateTimeString(),
        'created_at'      => $r->created_at?->toDateTimeString(),
        'updated_at'      => $r->updated_at?->toDateTimeString(),
        'ghi_chu'         => $r->ghi_chu,
    ];
}


    // --- Response helpers ---
    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }
        return response()->json(['success' => true, 'code' => $code, 'data' => $data], $status);
    }

    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }
        return response()->json(['success' => false, 'code' => $code, 'data' => $data], $status);
    }
}
