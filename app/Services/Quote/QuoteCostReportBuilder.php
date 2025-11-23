<?php

namespace App\Services\Quote;

use App\Models\QuoteCost;
use Illuminate\Support\Collection;

/**
 * Build dữ liệu CHI PHÍ (Đề xuất / Thực tế) cho PDF / Excel
 *
 * Đầu vào:  1 QuoteCost đã load items
 * Đầu ra:   mảng ['sections' => [...], 'totals' => [...]]
 *
 * sections:
 *  [
 *    [
 *      'code'           => 'NS',
 *      'letter'         => 'A',
 *      'name'           => 'Nhân sự',
 *      'total_cost'     => 123456,
 *      'total_revenue'  => 234567,
 *      'items' => [
 *        [
 *          'section_code'       => 'NS',
 *          'hang_muc'           => 'Âm thanh',
 *          'hang_muc_goc'       => 'Âm thanh',
 *          'chi_tiet'           => 'MC chuyên nghiệp...',
 *          'chi_tiet_html'      => null,
 *          'dvt'                => 'Người',
 *          'so_luong'           => 1,
 *          'supplier_name'      => 'PHG / NCC A',
 *          'cost_unit_price'    => 2200000,
 *          'cost_total_amount'  => 2200000,
 *          'sell_unit_price'    => 3000000,
 *          'sell_total_amount'  => 3000000,
 *          'is_package'         => false,
 *        ],
 *        ...
 *      ],
 *    ],
 *    ...
 *  ]
 *
 * totals:
 *  [
 *    'total_revenue'  => ...,
 *    'total_cost'     => ...,
 *    'total_margin'   => ...,
 *    'margin_percent' => ...,
 *  ]
 */
class QuoteCostReportBuilder
{
    /**
     * Định nghĩa nhóm hạng mục chuẩn theo section_code
     */
    protected array $sectionDefs = [
        'NS'   => ['letter' => 'A', 'name' => 'Nhân sự'],
        'CSVC' => ['letter' => 'B', 'name' => 'Cơ sở vật chất'],
        'TIEC' => ['letter' => 'C', 'name' => 'Tiệc'],
        'TD'   => ['letter' => 'D', 'name' => 'Thuê địa điểm'],
        'CPK'  => ['letter' => 'E', 'name' => 'Chi phí khác'],
        'OTHER'=> ['letter' => 'F', 'name' => 'Khác'],
    ];

    /**
     * Build dữ liệu cho 1 QuoteCost
     */
    public function build(QuoteCost $cost): array
    {
        // Đảm bảo đã load items
        $items = $cost->relationLoaded('items')
            ? $cost->items
            : $cost->items()->get();

        /** @var \Illuminate\Support\Collection $items */
        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        // Gom items theo section_code
        $sections = [];

        foreach ($items as $item) {
            /** @var \App\Models\QuoteCostItem $item */
            $secCode = strtoupper((string) ($item->section_code ?? ''));
            if ($secCode === '' || ! isset($this->sectionDefs[$secCode])) {
                $secCode = 'OTHER';
            }

            if (! isset($sections[$secCode])) {
                $sections[$secCode] = [
                    'code'          => $secCode,
                    'letter'        => $this->sectionDefs[$secCode]['letter'],
                    'name'          => $this->sectionDefs[$secCode]['name'],
                    'total_cost'    => 0,
                    'total_revenue' => 0,
                    'items'         => [],
                ];
            }

            // Hạng mục gốc
            $hmGoc   = (string) ($item->hang_muc_goc ?? '');
            $hangMuc = $hmGoc !== '' ? $hmGoc : $this->sectionDefs[$secCode]['name'];

            // Chi tiết / mô tả
            $chiTiet      = $item->description ?? null;
            $chiTietHtml  = null; // nếu sau này anh muốn cho phép HTML thì field này sẽ dùng

            // ĐVT
            $dvt = (string) ($item->dvt ?? '');

            // Số lượng
            $qty = (float) ($item->qty ?? 0);

            // NCC
            $supplierName = $item->supplier_name ?? '';

            // Giá chi phí / doanh thu
            $costUnit   = (int) ($item->cost_unit_price ?? 0);
            $costTotal  = (int) $item->cost_total_computed;
            $sellUnit   = (int) ($item->sell_unit_price ?? 0);
            $sellTotal  = (int) $item->sell_total_computed;

            // is_package: hiện tại chưa phân biệt gói/ thành phần, để false
            $isPackage = false;

            $sections[$secCode]['items'][] = [
                'section_code'      => $secCode,
                'hang_muc'          => $hangMuc,
                'hang_muc_goc'      => $hmGoc,
                'chi_tiet'          => $chiTiet,
                'chi_tiet_html'     => $chiTietHtml,
                'dvt'               => $dvt,
                'so_luong'          => $qty,
                'supplier_name'     => $supplierName,
                'cost_unit_price'   => $costUnit,
                'cost_total_amount' => $costTotal,
                'sell_unit_price'   => $sellUnit,
                'sell_total_amount' => $sellTotal,
                'is_package'        => $isPackage,
            ];

            $sections[$secCode]['total_cost']    += $costTotal;
            $sections[$secCode]['total_revenue'] += $sellTotal;
        }

        // Tính tổng toàn bảng
        $totalCost    = 0;
        $totalRevenue = 0;

        foreach ($sections as $code => $sec) {
            $totalCost    += (int) ($sec['total_cost']    ?? 0);
            $totalRevenue += (int) ($sec['total_revenue'] ?? 0);
        }

        $totalMargin   = $totalRevenue - $totalCost;
        $marginPercent = $totalRevenue > 0 ? round($totalMargin * 100 / $totalRevenue, 2) : null;

        // Nếu header QuoteCost đã có tổng, ưu tiên dùng (để sync với UI QLCP)
        $headerRevenue  = (int) ($cost->total_revenue ?? 0);
        $headerCost     = (int) ($cost->total_cost     ?? 0);
        $headerMargin   = (int) ($cost->total_margin   ?? 0);
        $headerMarginPc = $cost->margin_percent !== null ? (float) $cost->margin_percent : null;

        // Nếu header có giá trị > 0 thì override
        if ($headerRevenue > 0 || $headerCost > 0) {
            $totalRevenue  = $headerRevenue;
            $totalCost     = $headerCost;
            $totalMargin   = $headerMargin !== 0 ? $headerMargin : ($headerRevenue - $headerCost);
            $marginPercent = $headerMarginPc ?? ($totalRevenue > 0 ? round($totalMargin * 100 / $totalRevenue, 2) : null);
        }

        // Chuẩn hoá sections thành array (loại bỏ 'OTHER' nếu không có item)
        $sectionsList = array_values(array_filter(
            $sections,
            fn ($sec) => !empty($sec['items'])
        ));

        return [
            'sections' => $sectionsList,
            'totals'   => [
                'total_revenue'  => $totalRevenue,
                'total_cost'     => $totalCost,
                'total_margin'   => $totalMargin,
                'margin_percent' => $marginPercent,
            ],
        ];
    }
}
