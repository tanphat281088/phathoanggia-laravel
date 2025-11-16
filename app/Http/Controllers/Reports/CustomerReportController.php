<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\CustomerReportService;
use App\Class\CustomResponse;

class CustomerReportController extends Controller
{
    public function __construct(
        protected CustomerReportService $svc
    ) {
        //
    }

    /**
     * GET /api/bao-cao-quan-tri/khach-hang/summary
     *
     * Báo cáo khách hàng tổng hợp (READ-ONLY).
     *
     * Query:
     *  - from: YYYY-MM-DD (optional)
     *  - to:   YYYY-MM-DD (optional, >= from)
     *
     * Nếu không truyền from/to:
     *  - to   = hôm nay
     *  - from = ngày đầu tháng của to (sẽ xử lý trong service).
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? null;
        $to   = $validated['to']   ?? null;

        $data = $this->svc->summary($from, $to);

        return CustomResponse::success($data);
    }
}
