<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerReportService
{
    /**
     * Báo cáo khách hàng tổng hợp (READ-ONLY).
     *
     * JSON trả về gồm:
     *  - params   : thông tin kỳ lọc
     *  - kpi      : KPI tổng quan khách hàng
     *  - segments : phân khúc (hạng, mode, kênh)
     *  - top_customers : Top khách hàng (lifetime & theo kỳ)
     *  - messaging: Zalo ZNS (tích điểm & review)
     *  - behavior : hành vi khách hàng (mới/quay lại, tần suất...)
     *  - loyalty  : tổng quan điểm & hạng trung thành
     */
    public function summary(?string $from, ?string $to): array
    {
        // ===== Chuẩn hoá khoảng ngày =====
        $endDate   = $to   ?: date('Y-m-d');
        $startDate = $from ?: $this->firstDayOfMonth($endDate);

        // ===== Các cột hữu ích =====
        $hasTrangThaiDH = $this->columnExists('don_hangs', 'trang_thai_don_hang');
        $hasNgayGiao    = $this->columnExists('don_hangs', 'nguoi_nhan_thoi_gian');
        $hasNgayTaoDH   = $this->columnExists('don_hangs', 'ngay_tao_don_hang');

        // Cột ngày dùng cho "ngày đơn" (lifetime/behavior)
        $colNgayDon = $hasNgayGiao
            ? 'nguoi_nhan_thoi_gian'
            : ($hasNgayTaoDH ? 'ngay_tao_don_hang' : 'created_at');

        // Cột ngày dùng cho lọc doanh thu trong kỳ (ưu tiên ngày giao)
        $colNgayDoanhThu = $hasNgayGiao ? 'nguoi_nhan_thoi_gian' : $colNgayDon;

        $ratePoint = (int) env('POINT_VND_RATE', 1000);
        if ($ratePoint <= 0) {
            $ratePoint = 1000;
        }

        // =========================
        // 1. KPI TỔNG QUAN
        // =========================
        $totalCustomers  = (int) DB::table('khach_hangs')->count();
        $activeCustomers = (int) DB::table('khach_hangs')->where('trang_thai', 1)->count();

        $newCustomers = (int) DB::table('khach_hangs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        // Đơn đã giao trong kỳ (để tính doanh thu & khách có đơn)
        $baseDeliveredPeriod = DB::table('don_hangs')
            ->whereNotNull('khach_hang_id');

        if ($hasTrangThaiDH) {
            $baseDeliveredPeriod->where('trang_thai_don_hang', 2); // Đã giao
        }
        $baseDeliveredPeriod
            ->whereNotNull($colNgayDoanhThu)
            ->whereDate($colNgayDoanhThu, '>=', $startDate)
            ->whereDate($colNgayDoanhThu, '<=', $endDate);

        $revenuePeriod = (int) (clone $baseDeliveredPeriod)
            ->sum('tong_tien_can_thanh_toan');

        $ordersPeriod = (int) (clone $baseDeliveredPeriod)->count();

        $customersWithOrdersPeriod = (int) DB::query()
            ->fromSub(
                (clone $baseDeliveredPeriod)->select('khach_hang_id'),
                'x'
            )
            ->whereNotNull('khach_hang_id')
            ->distinct()
            ->count('khach_hang_id');

        // Đơn lifetime (exclude huỷ) để tính behavior
        $baseOrdersLifetime = DB::table('don_hangs')
            ->whereNotNull('khach_hang_id')
            ->whereNotNull($colNgayDon);

        if ($hasTrangThaiDH) {
            $baseOrdersLifetime->where('trang_thai_don_hang', '!=', 3); // 3 = Đã huỷ
        }

        $ordersAllLifetime = (int) (clone $baseOrdersLifetime)->count();

        $customersWithAnyOrdersLifetime = (int) DB::query()
            ->fromSub(
                (clone $baseOrdersLifetime)->select('khach_hang_id'),
                'y'
            )
            ->distinct()
            ->count('khach_hang_id');

        $avgOrdersPerCustomer = $customersWithAnyOrdersLifetime > 0
            ? round($ordersAllLifetime / $customersWithAnyOrdersLifetime, 2)
            : 0.0;

        // Doanh thu tích luỹ (lifetime) từ bảng KH
        $totalRevenueLifetime = (int) DB::table('khach_hangs')->sum('doanh_thu_tich_luy');

        $avgRevenuePerCustomer = $totalCustomers > 0
            ? (int) floor($totalRevenueLifetime / $totalCustomers)
            : 0;

        // Điểm (ước lượng): tổng doanh thu / rate
        $totalPointsAllCustomers = (int) floor($totalRevenueLifetime / $ratePoint);
        $avgPointsPerCustomer    = $totalCustomers > 0
            ? (int) floor($totalPointsAllCustomers / $totalCustomers)
            : 0;

        $customersWithPoints = (int) DB::table('khach_hangs')
            ->where('doanh_thu_tich_luy', '>', 0)
            ->count();

        // First-time vs Returning trong kỳ
        $firstOrders = DB::query()
            ->fromSub(
                (clone $baseOrdersLifetime)
                    ->selectRaw("khach_hang_id, DATE(MIN($colNgayDon)) as first_date")
                    ->groupBy('khach_hang_id'),
                'fo'
            )
            ->get();

        $firstTimeBuyersPeriod = 0;
        foreach ($firstOrders as $r) {
            if ($r->first_date >= $startDate && $r->first_date <= $endDate) {
                $firstTimeBuyersPeriod++;
            }
        }

        $returningCustomersPeriod = max(
            0,
            $customersWithOrdersPeriod - $firstTimeBuyersPeriod
        );

        $repeatRatePeriod = $customersWithOrdersPeriod > 0
            ? round($returningCustomersPeriod / $customersWithOrdersPeriod, 4)
            : 0.0;

        $kpi = [
            'total_customers'               => $totalCustomers,
            'active_customers'              => $activeCustomers,
            'new_customers'                 => $newCustomers,
            'customers_with_orders_period'  => $customersWithOrdersPeriod,
            'first_time_buyers_period'      => $firstTimeBuyersPeriod,
            'returning_customers_period'    => $returningCustomersPeriod,
            'repeat_rate_period'            => $repeatRatePeriod,
            'customers_with_points'         => $customersWithPoints,
            'avg_points_per_customer'       => $avgPointsPerCustomer,
            'total_revenue_lifetime'        => $totalRevenueLifetime,
            'revenue_period'                => $revenuePeriod,
            'avg_revenue_per_customer'      => $avgRevenuePerCustomer,
            'avg_orders_per_customer'       => $avgOrdersPerCustomer,
        ];

        // =========================
        // 2. SUBQUERY DOANH THU / ĐƠN THEO KHÁCH HÀNG (TRONG KỲ)
        // =========================
        $ordersPeriodByCustomer = DB::table('don_hangs')
            ->whereNotNull('khach_hang_id');

        if ($hasTrangThaiDH) {
            $ordersPeriodByCustomer->where('trang_thai_don_hang', '!=', 3);
        }

        $ordersPeriodByCustomer
            ->whereNotNull($colNgayDoanhThu)
            ->whereDate($colNgayDoanhThu, '>=', $startDate)
            ->whereDate($colNgayDoanhThu, '<=', $endDate)
            ->selectRaw("
                khach_hang_id,
                COUNT(*) as orders_period,
                SUM(tong_tien_can_thanh_toan) as revenue_period
            ")
            ->groupBy('khach_hang_id');

        // =========================
        // 3. SEGMENTS
        // =========================

        // 3.1 Theo loại khách hàng (hạng thành viên)
        $tiersRaw = DB::table('loai_khach_hangs as t')
            ->leftJoin('khach_hangs as kh', 'kh.loai_khach_hang_id', '=', 't.id')
            ->leftJoinSub($ordersPeriodByCustomer, 'o', 'o.khach_hang_id', '=', 'kh.id')
            ->selectRaw("
                t.id,
                t.ten_loai_khach_hang,
                t.gia_tri_uu_dai,
                t.nguong_doanh_thu,
                t.nguong_diem,
                COUNT(kh.id)                                   as customer_count,
                SUM(CASE WHEN kh.trang_thai = 1 THEN 1 ELSE 0 END) as active_customer_count,
                COALESCE(SUM(kh.doanh_thu_tich_luy), 0)       as revenue_lifetime,
                COALESCE(SUM(o.revenue_period), 0)            as revenue_period,
                COALESCE(SUM(o.orders_period), 0)             as orders_period
            ")
            ->groupBy(
                't.id',
                't.ten_loai_khach_hang',
                't.gia_tri_uu_dai',
                't.nguong_doanh_thu',
                't.nguong_diem'
            )
            ->get();

        $segmentsByTier = [];
        foreach ($tiersRaw as $t) {
            $customerCount = (int) $t->customer_count;
            $ordersPeriodT = (int) $t->orders_period;
            $revenueLifetimeT = (int) $t->revenue_lifetime;
            $revenuePeriodT   = (int) $t->revenue_period;

            $segmentsByTier[] = [
                'tier_id'                => (int) $t->id,
                'tier_name'              => (string) $t->ten_loai_khach_hang,
                'tier_group'             => $this->guessTierGroup((int) $t->gia_tri_uu_dai, (int) $t->nguong_doanh_thu),
                'gia_tri_uu_dai_pct'     => (int) $t->gia_tri_uu_dai,
                'threshold_revenue'      => (int) $t->nguong_doanh_thu,
                'threshold_points'       => (int) $t->nguong_diem,
                'customer_count'         => $customerCount,
                'active_customer_count'  => (int) $t->active_customer_count,
                'revenue_lifetime'       => $revenueLifetimeT,
                'avg_revenue_lifetime'   => $customerCount > 0 ? (int) floor($revenueLifetimeT / $customerCount) : 0,
                'revenue_period'         => $revenuePeriodT,
                'orders_period'          => $ordersPeriodT,
                'avg_order_value_period' => $ordersPeriodT > 0 ? (int) floor($revenuePeriodT / $ordersPeriodT) : 0,
            ];
        }

        // 3.2 Theo customer_mode (0 = thường, 1 = Pass/CTV)
        $modesRaw = DB::table('khach_hangs as kh')
            ->leftJoinSub($ordersPeriodByCustomer, 'o', 'o.khach_hang_id', '=', 'kh.id')
            ->selectRaw("
                COALESCE(kh.customer_mode, 0) as mode,
                COUNT(kh.id)                  as customer_count,
                COALESCE(SUM(kh.doanh_thu_tich_luy), 0) as revenue_lifetime,
                COALESCE(SUM(o.revenue_period), 0)      as revenue_period,
                COALESCE(SUM(o.orders_period), 0)       as orders_period
            ")
            ->groupBy('mode')
            ->get();

        $segmentsByMode = [];
        foreach ($modesRaw as $m) {
            $mode = (int) $m->mode;
            $label = $mode === 1 ? 'Khách hàng Pass đơn & CTV' : 'Khách hàng hệ thống';
            $custCount = (int) $m->customer_count;
            $revPeriodM = (int) $m->revenue_period;
            $ordersPeriodM = (int) $m->orders_period;

            $segmentsByMode[] = [
                'mode'               => $mode,
                'label'              => $label,
                'customer_count'     => $custCount,
                'revenue_lifetime'   => (int) $m->revenue_lifetime,
                'revenue_period'     => $revPeriodM,
                'orders_period'      => $ordersPeriodM,
                'avg_order_value_period' => $ordersPeriodM > 0 ? (int) floor($revPeriodM / $ordersPeriodM) : 0,
            ];
        }

        // 3.3 Theo kênh liên hệ (kenh_lien_he)
        $channelsRaw = DB::table('khach_hangs as kh')
            ->leftJoinSub($ordersPeriodByCustomer, 'o', 'o.khach_hang_id', '=', 'kh.id')
            ->selectRaw("
                kh.kenh_lien_he as kenh,
                COUNT(kh.id)    as customer_count,
                SUM(CASE WHEN DATE(kh.created_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_customers_period,
                COALESCE(SUM(kh.doanh_thu_tich_luy),0) as revenue_lifetime,
                COALESCE(SUM(o.revenue_period),0)      as revenue_period,
                COALESCE(SUM(o.orders_period),0)       as orders_period,
                COALESCE(SUM(CASE WHEN o.orders_period >= 1 THEN 1 ELSE 0 END),0) as customers_with_orders_period,
                COALESCE(SUM(CASE WHEN o.orders_period >= 2 THEN 1 ELSE 0 END),0) as repeat_customers_period
            ", [$startDate, $endDate])
            ->groupBy('kenh')
            ->get();

        $segmentsByChannel = [];
        foreach ($channelsRaw as $c) {
            $custCount = (int) $c->customer_count;
            $ordersPeriodC = (int) $c->orders_period;
            $revPeriodC    = (int) $c->revenue_period;
            $custWithOrders= (int) $c->customers_with_orders_period;
            $repeatCust    = (int) $c->repeat_customers_period;

            $segmentsByChannel[] = [
                'kenh'                     => $c->kenh ?: 'Khác/Chưa xác định',
                'customer_count'           => $custCount,
                'new_customers_period'     => (int) $c->new_customers_period,
                'revenue_lifetime'         => (int) $c->revenue_lifetime,
                'revenue_period'           => $revPeriodC,
                'orders_period'            => $ordersPeriodC,
                'avg_order_value_period'   => $ordersPeriodC > 0 ? (int) floor($revPeriodC / $ordersPeriodC) : 0,
                'customers_with_orders_period' => $custWithOrders,
                'repeat_customers_period'  => $repeatCust,
                'repeat_rate_period_by_channel' => $custWithOrders > 0
                    ? round($repeatCust / $custWithOrders, 4)
                    : 0.0,
            ];
        }

        $segments = [
            'by_tier'    => $segmentsByTier,
            'by_mode'    => $segmentsByMode,
            'by_channel' => $segmentsByChannel,
        ];

        // =========================
        // 4. TOP CUSTOMERS
        // =========================

        // 4.1 Aggregation lifetime
        $ordersLifetimeAgg = (clone $baseOrdersLifetime)
            ->selectRaw("
                khach_hang_id,
                COUNT(*)                          as total_orders,
                SUM(tong_tien_can_thanh_toan)    as total_revenue,
                MAX($colNgayDon)                 as last_order_date
            ")
            ->groupBy('khach_hang_id');

        $topLifetimeRaw = DB::table('khach_hangs as kh')
            ->leftJoinSub($ordersLifetimeAgg, 'o', 'o.khach_hang_id', '=', 'kh.id')
            ->leftJoin('loai_khach_hangs as t', 't.id', '=', 'kh.loai_khach_hang_id')
            ->selectRaw("
                kh.id,
                kh.ma_kh,
                kh.ten_khach_hang,
                kh.so_dien_thoai,
                kh.kenh_lien_he,
                COALESCE(kh.customer_mode,0) as customer_mode,
                t.ten_loai_khach_hang,
                t.gia_tri_uu_dai,
                COALESCE(kh.doanh_thu_tich_luy,0)         as doanh_thu_tich_luy,
                COALESCE(o.total_orders,0)                 as total_orders,
                COALESCE(o.total_revenue,0)                as total_revenue,
                o.last_order_date
            ")
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $topLifetime = [];
        foreach ($topLifetimeRaw as $r) {
            $points = (int) floor(((int) $r->doanh_thu_tich_luy) / $ratePoint);
            $topLifetime[] = [
                'khach_hang_id'   => (int) $r->id,
                'ma_kh'           => $r->ma_kh,
                'ten_khach_hang'  => $r->ten_khach_hang,
                'so_dien_thoai'   => $r->so_dien_thoai,
                'kenh_lien_he'    => $r->kenh_lien_he,
                'customer_mode'   => (int) $r->customer_mode,
                'loai_khach_hang' => $r->ten_loai_khach_hang,
                'tier_group'      => $this->guessTierGroup((int) $r->gia_tri_uu_dai, 0),
                'total_revenue'   => (int) $r->total_revenue,
                'total_orders'    => (int) $r->total_orders,
                'aov'             => ((int) $r->total_orders) > 0
                    ? (int) floor((int) $r->total_revenue / (int) $r->total_orders)
                    : 0,
                'last_order_date' => $r->last_order_date,
                'current_points'  => $points,
            ];
        }

        // 4.2 Top customers trong kỳ (dựa trên ordersPeriodByCustomer)
        $topPeriodRaw = DB::table('khach_hangs as kh')
            ->joinSub($ordersPeriodByCustomer, 'o', 'o.khach_hang_id', '=', 'kh.id')
            ->leftJoin('loai_khach_hangs as t', 't.id', '=', 'kh.loai_khach_hang_id')
            ->selectRaw("
                kh.id,
                kh.ma_kh,
                kh.ten_khach_hang,
                kh.so_dien_thoai,
                kh.kenh_lien_he,
                COALESCE(kh.customer_mode,0) as customer_mode,
                t.ten_loai_khach_hang,
                t.gia_tri_uu_dai,
                COALESCE(kh.doanh_thu_tich_luy,0) as doanh_thu_tich_luy,
                o.revenue_period,
                o.orders_period
            ")
            ->orderByDesc('o.revenue_period')
            ->limit(10)
            ->get();

        $topPeriod = [];
        foreach ($topPeriodRaw as $r) {
            $points = (int) floor(((int) $r->doanh_thu_tich_luy) / $ratePoint);
            $topPeriod[] = [
                'khach_hang_id'   => (int) $r->id,
                'ma_kh'           => $r->ma_kh,
                'ten_khach_hang'  => $r->ten_khach_hang,
                'so_dien_thoai'   => $r->so_dien_thoai,
                'kenh_lien_he'    => $r->kenh_lien_he,
                'customer_mode'   => (int) $r->customer_mode,
                'loai_khach_hang' => $r->ten_loai_khach_hang,
                'tier_group'      => $this->guessTierGroup((int) $r->gia_tri_uu_dai, 0),
                'total_revenue'   => (int) $r->revenue_period,
                'total_orders'    => (int) $r->orders_period,
                'aov'             => ((int) $r->orders_period) > 0
                    ? (int) floor((int) $r->revenue_period / (int) $r->orders_period)
                    : 0,
                'last_order_date' => null, // nếu cần có thể join thêm từ ordersPeriodAgg
                'current_points'  => $points,
            ];
        }

        $topCustomers = [
            'lifetime' => $topLifetime,
            'period'   => $topPeriod,
        ];

        // =========================
        // 5. MESSAGING (ZNS)
        // =========================
        $messaging = [
            'points_zns' => $this->buildPointsZnsStats($startDate, $endDate),
            'review_zns' => $this->buildReviewZnsStats($startDate, $endDate, $hasTrangThaiDH, $colNgayDoanhThu),
        ];

        // =========================
        // 6. HÀNH VI KHÁCH HÀNG
        // =========================
        $behavior = $this->buildBehaviorStats(
            $startDate,
            $endDate,
            $baseOrdersLifetime,
            $colNgayDon,
            $firstOrders,
            $customersWithOrdersPeriod,
            $firstTimeBuyersPeriod
        );

        // =========================
        // 7. LOYALTY / HẠNG TRUNG THÀNH
        // =========================

        // KH có điểm cao nhất
        $maxPointsCustomerRow = DB::table('khach_hangs')
            ->select('id', 'ma_kh', 'ten_khach_hang', 'so_dien_thoai', 'doanh_thu_tich_luy')
            ->orderByDesc('doanh_thu_tich_luy')
            ->first();

        $maxPointsCustomer = null;
        if ($maxPointsCustomerRow) {
            $maxPointsCustomer = [
                'khach_hang_id'  => (int) $maxPointsCustomerRow->id,
                'ma_kh'          => $maxPointsCustomerRow->ma_kh,
                'ten_khach_hang' => $maxPointsCustomerRow->ten_khach_hang,
                'so_dien_thoai'  => $maxPointsCustomerRow->so_dien_thoai,
                'points'         => (int) floor(((int) $maxPointsCustomerRow->doanh_thu_tich_luy) / $ratePoint),
            ];
        }

        $loyalty = [
            'overview' => [
                'total_points_all_customers' => $totalPointsAllCustomers,
                'avg_points_per_customer'    => $avgPointsPerCustomer,
                'max_points_customer'        => $maxPointsCustomer,
            ],
            // Dùng lại segments.by_tier cho tier_summary
            'tier_summary' => $segmentsByTier,
        ];

        return [
            'params'   => ['from' => $startDate, 'to' => $endDate],
            'kpi'      => $kpi,
            'segments' => $segments,
            'top_customers' => $topCustomers,
            'messaging' => $messaging,
            'behavior'  => $behavior,
            'loyalty'   => $loyalty,
        ];
    }

    // =========================
    //  Helpers cho Messaging
    // =========================

    private function buildPointsZnsStats(string $startDate, string $endDate): array
    {
        if (!$this->columnExists('khach_hang_point_events', 'id')) {
            return [
                'total_events_lifetime'         => 0,
                'total_events_period'           => 0,
                'events_sent_lifetime'          => 0,
                'events_failed_lifetime'        => 0,
                'events_pending_lifetime'       => 0,
                'events_sent_period'            => 0,
                'events_failed_period'          => 0,
                'events_pending_period'         => 0,
                'customers_with_events_lifetime'=> 0,
                'customers_with_events_period'  => 0,
                'customers_with_sent_period'    => 0,
                'coverage_rate_period'          => 0.0,
                'points_added_period'           => 0,
                'points_reversed_period'        => 0,
            ];
        }

        $base = DB::table('khach_hang_point_events');

        $totalEventsLifetime = (int) $base->count();
        $lifetimeByStatus = DB::table('khach_hang_point_events')
            ->selectRaw('zns_status, COUNT(*) as c')
            ->groupBy('zns_status')
            ->pluck('c', 'zns_status')
            ->toArray();

        $periodBase = DB::table('khach_hang_point_events')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate);

        $totalEventsPeriod = (int) $periodBase->count();

        $periodByStatus = DB::query()
            ->fromSub(
                DB::table('khach_hang_point_events')
                    ->whereDate('order_date', '>=', $startDate)
                    ->whereDate('order_date', '<=', $endDate)
                    ->selectRaw('zns_status, COUNT(*) as c')
                    ->groupBy('zns_status'),
                't'
            )
            ->pluck('c', 'zns_status')
            ->toArray();

        $customersWithEventsLifetime = (int) DB::table('khach_hang_point_events')
            ->distinct()
            ->count('khach_hang_id');

        $customersWithEventsPeriod = (int) DB::table('khach_hang_point_events')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->distinct()
            ->count('khach_hang_id');

        $customersWithSentPeriod = (int) DB::table('khach_hang_point_events')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->where('zns_status', 'sent')
            ->distinct()
            ->count('khach_hang_id');

        $pointsAddedPeriod = (int) DB::table('khach_hang_point_events')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->where('delta_points', '>', 0)
            ->sum('delta_points');

        $pointsReversedPeriod = (int) DB::table('khach_hang_point_events')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->where('delta_points', '<', 0)
            ->sum('delta_points');

        $coverageRatePeriod = $customersWithEventsPeriod > 0
            ? round($customersWithSentPeriod / $customersWithEventsPeriod, 4)
            : 0.0;

        return [
            'total_events_lifetime'          => $totalEventsLifetime,
            'total_events_period'            => $totalEventsPeriod,
            'events_sent_lifetime'           => (int) ($lifetimeByStatus['sent'] ?? 0),
            'events_failed_lifetime'         => (int) ($lifetimeByStatus['failed'] ?? 0),
            'events_pending_lifetime'        => (int) ($lifetimeByStatus['pending'] ?? 0),
            'events_sent_period'             => (int) ($periodByStatus['sent'] ?? 0),
            'events_failed_period'           => (int) ($periodByStatus['failed'] ?? 0),
            'events_pending_period'          => (int) ($periodByStatus['pending'] ?? 0),
            'customers_with_events_lifetime' => $customersWithEventsLifetime,
            'customers_with_events_period'   => $customersWithEventsPeriod,
            'customers_with_sent_period'     => $customersWithSentPeriod,
            'coverage_rate_period'           => $coverageRatePeriod,
            'points_added_period'            => $pointsAddedPeriod,
            'points_reversed_period'         => $pointsReversedPeriod,
        ];
    }

    private function buildReviewZnsStats(string $startDate, string $endDate, bool $hasTrangThaiDH, string $colNgayDoanhThu): array
    {
        if (!$this->columnExists('zns_review_invites', 'id')) {
            return [
                'invites_total_lifetime'  => 0,
                'invites_total_period'    => 0,
                'invites_pending_period'  => 0,
                'invites_sent_period'     => 0,
                'invites_failed_period'   => 0,
                'invites_cancelled_period'=> 0,
                'unique_customers_period' => 0,
                'orders_with_invite_period'=> 0,
                'eligible_orders_period'  => 0,
                'coverage_rate_period'    => 0.0,
                'success_rate_period'     => 0.0,
            ];
        }

        $base = DB::table('zns_review_invites');

        $invitesTotalLifetime = (int) $base->count();

        $periodBase = DB::table('zns_review_invites')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate);

        $invitesTotalPeriod = (int) $periodBase->count();

        $statusCounts = DB::query()
            ->fromSub(
                DB::table('zns_review_invites')
                    ->whereDate('order_date', '>=', $startDate)
                    ->whereDate('order_date', '<=', $endDate)
                    ->selectRaw('zns_status, COUNT(*) as c')
                    ->groupBy('zns_status'),
                't'
            )
            ->pluck('c', 'zns_status')
            ->toArray();

        $uniqueCustomersPeriod = (int) DB::table('zns_review_invites')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->distinct()
            ->count('khach_hang_id');

        $ordersWithInvitePeriod = (int) DB::table('zns_review_invites')
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->distinct()
            ->count('don_hang_id');

        // Đơn đủ điều kiện mời review: đã giao & đã thanh toán
        $eligibleOrders = DB::table('don_hangs')
            ->whereNotNull('khach_hang_id');

        if ($hasTrangThaiDH) {
            $eligibleOrders->where('trang_thai_don_hang', 2);
        }

        $eligibleOrders
            ->whereNotNull($colNgayDoanhThu)
            ->whereDate($colNgayDoanhThu, '>=', $startDate)
            ->whereDate($colNgayDoanhThu, '<=', $endDate)
            ->where(function ($w) {
                $w->where('trang_thai_thanh_toan', 1)
                  ->orWhere('loai_thanh_toan', 2);
            });

        $eligibleOrdersPeriod = (int) $eligibleOrders->count();

        $coverageRate = $eligibleOrdersPeriod > 0
            ? round($ordersWithInvitePeriod / $eligibleOrdersPeriod, 4)
            : 0.0;

        $invitesSentPeriod    = (int) ($statusCounts['sent'] ?? 0);
        $successRate          = $invitesTotalPeriod > 0
            ? round($invitesSentPeriod / $invitesTotalPeriod, 4)
            : 0.0;

        return [
            'invites_total_lifetime'   => $invitesTotalLifetime,
            'invites_total_period'     => $invitesTotalPeriod,
            'invites_pending_period'   => (int) ($statusCounts['pending'] ?? 0),
            'invites_sent_period'      => $invitesSentPeriod,
            'invites_failed_period'    => (int) ($statusCounts['failed'] ?? 0),
            'invites_cancelled_period' => (int) ($statusCounts['cancelled'] ?? 0),
            'unique_customers_period'  => $uniqueCustomersPeriod,
            'orders_with_invite_period'=> $ordersWithInvitePeriod,
            'eligible_orders_period'   => $eligibleOrdersPeriod,
            'coverage_rate_period'     => $coverageRate,
            'success_rate_period'      => $successRate,
        ];
    }

    // =========================
    //  Helpers cho Behavior
    // =========================
    private function buildBehaviorStats(
        string $startDate,
        string $endDate,
        $baseOrdersLifetime,
        string $colNgayDon,
        $firstOrders,
        int $customersWithOrdersPeriod,
        int $firstTimeBuyersPeriod
    ): array {
        // Tổng hợp theo khách hàng: first/last date + total_orders
        $orderStats = DB::query()
            ->fromSub(
                (clone $baseOrdersLifetime)
                    ->selectRaw("
                        khach_hang_id,
                        DATE(MIN($colNgayDon)) as first_date,
                        DATE(MAX($colNgayDon)) as last_date,
                        COUNT(*) as total_orders
                    ")
                    ->groupBy('khach_hang_id'),
                's'
            )
            ->get();

        $oneTime = 0;
        $twoToThree = 0;
        $moreThanThree = 0;

        $active_0_30 = 0;
        $warm_31_90  = 0;
        $cold_91_plus = 0;

        $avgIntervalSum = 0.0;
        $avgIntervalCount = 0;

        $endTs = strtotime($endDate . ' 00:00:00');

        foreach ($orderStats as $s) {
            $totalOrders = (int) $s->total_orders;

            if ($totalOrders <= 1) {
                $oneTime++;
            } elseif ($totalOrders <= 3) {
                $twoToThree++;
            } else {
                $moreThanThree++;
            }

            // Recency
            if (!empty($s->last_date)) {
                $lastTs = strtotime($s->last_date . ' 00:00:00');
                $diffDays = (int) floor(($endTs - $lastTs) / 86400);

                if ($diffDays <= 30) {
                    $active_0_30++;
                } elseif ($diffDays <= 90) {
                    $warm_31_90++;
                } else {
                    $cold_91_plus++;
                }
            }

            // Approx interval (span / (n-1))
            if ($totalOrders > 1 && !empty($s->first_date) && !empty($s->last_date)) {
                $firstTs = strtotime($s->first_date . ' 00:00:00');
                $lastTs  = strtotime($s->last_date  . ' 00:00:00');
                if ($lastTs > $firstTs) {
                    $spanDays = (int) floor(($lastTs - $firstTs) / 86400);
                    $interval = $spanDays / max(1, $totalOrders - 1);
                    if ($interval > 0) {
                        $avgIntervalSum   += $interval;
                        $avgIntervalCount += 1;
                    }
                }
            }
        }

        $avgDaysBetweenOrders = $avgIntervalCount > 0
            ? round($avgIntervalSum / $avgIntervalCount, 1)
            : null;

        $behavior = [
            'first_time_buyers_period'   => $firstTimeBuyersPeriod,
            'returning_customers_period' => max(0, $customersWithOrdersPeriod - $firstTimeBuyersPeriod),
            'repeat_rate_period'         => $customersWithOrdersPeriod > 0
                ? round(
                    max(0, $customersWithOrdersPeriod - $firstTimeBuyersPeriod) / $customersWithOrdersPeriod,
                    4
                )
                : 0.0,
            'avg_days_between_orders'    => $avgDaysBetweenOrders,
            'orders_per_customer_distribution' => [
                'one_time_buyers'        => $oneTime,
                'two_to_three_orders'    => $twoToThree,
                'more_than_three_orders' => $moreThanThree,
            ],
            'recency_segments' => [
                'active_0_30'  => $active_0_30,
                'warm_31_90'   => $warm_31_90,
                'cold_91_plus' => $cold_91_plus,
            ],
        ];

        return $behavior;
    }

    // =========================
    //  Helpers chung
    // =========================

    private function firstDayOfMonth(string $ymd): string
    {
        $ts = strtotime($ymd . ' 00:00:00');
        return date('Y-m-01', $ts);
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            DB::table($table)->select($column)->limit(0)->get();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Suy đoán "tier_group" (bronze / silver / gold / platinum)
     * dựa trên % ưu đãi hoặc ngưỡng doanh thu.
     */
    private function guessTierGroup(int $giaTriUuDai, int $thresholdRevenue): string
    {
        // Ưu tiên theo phần trăm ưu đãi
        if ($giaTriUuDai >= 20 || $thresholdRevenue >= 50000000) {
            return 'platinum';
        }
        if ($giaTriUuDai >= 10 || $thresholdRevenue >= 20000000) {
            return 'gold';
        }
        if ($giaTriUuDai >= 5  || $thresholdRevenue >= 10000000) {
            return 'silver';
        }
        return 'bronze';
    }
}
