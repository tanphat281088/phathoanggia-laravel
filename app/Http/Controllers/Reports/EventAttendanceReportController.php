<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Timesheet\EventAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Báo cáo chấm công theo ĐỊA ĐIỂM (event site) + NHÂN VIÊN.
 *
 * GET /api/bao-cao-quan-tri/cham-cong-event?from=&to=&workpoint_id=&user_id=
 *
 * Query:
 *  - from        : YYYY-MM-DD (bắt đầu, optional – default = đầu tháng hiện tại)
 *  - to          : YYYY-MM-DD (kết thúc, optional – default = cuối tháng hiện tại)
 *  - workpoint_id: int, optional (lọc 1 địa điểm)
 *  - user_id     : int, optional (lọc 1 nhân viên)
 */
class EventAttendanceReportController extends Controller
{
    public function index(Request $request, EventAttendanceReportService $svc)
    {
        // ===== 1. Validate input =====
        $v = Validator::make($request->all(), [
            'from'         => ['nullable', 'date_format:Y-m-d'],
            'to'           => ['nullable', 'date_format:Y-m-d'],
            'workpoint_id' => ['nullable', 'integer', 'min:1'],
            'user_id'      => ['nullable', 'integer', 'min:1'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        try {
            // ===== 2. Chuẩn hoá khoảng ngày =====
            $today = Carbon::today(config('app.timezone', 'Asia/Ho_Chi_Minh'));

            // Default: từ đầu tháng -> cuối tháng hiện tại
            $fromStr = $request->input('from') ?: $today->copy()->startOfMonth()->toDateString();
            $toStr   = $request->input('to')   ?: $today->copy()->endOfMonth()->toDateString();

            $from = Carbon::createFromFormat('Y-m-d', $fromStr)->startOfDay();
            $to   = Carbon::createFromFormat('Y-m-d', $toStr)->endOfDay();

            if ($from->gt($to)) {
                // swap nếu user nhập ngược
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            $workpointId = $request->input('workpoint_id') ? (int) $request->input('workpoint_id') : null;
            $userId      = $request->input('user_id')      ? (int) $request->input('user_id')      : null;

            // ===== 3. Gọi service =====
            $report = $svc->report($from, $to, $workpointId, $userId);

            // Thêm filter để FE hiển thị lại
            $payload = [
                'filter' => [
                    'from'         => $from->toDateString(),
                    'to'           => $to->toDateString(),
                    'workpoint_id' => $workpointId,
                    'user_id'      => $userId,
                ],
                'data'   => $report,
            ];

            return $this->success($payload, 'EVENT_ATTENDANCE_REPORT');
        } catch (Throwable $e) {
            $msg = config('app.debug') ? $e->getMessage() : 'Không thể lấy báo cáo chấm công sự kiện.';
            return $this->failed(['message' => $msg], 'SERVER_ERROR', 500);
        }
    }

    // ===== Response helpers (giống pattern các Reports khác) =====

    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }

        return response()->json([
            'success' => true,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }

    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }

        return response()->json([
            'success' => false,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }
}
