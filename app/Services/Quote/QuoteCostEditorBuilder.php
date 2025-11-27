<?php

namespace App\Services\Quote;

use App\Models\QuoteCost;
use Illuminate\Support\Collection;

/**
 * QuoteCostEditorBuilder
 *
 * Dùng cho màn hình EDIT chi phí (modal lớn giống Báo giá):
 *  - Merge layout báo giá từ QuoteBuilder (DonHang + ChiTietDonHang)
 *  - Với dữ liệu chi phí từ QuoteCostItem (SUP, đơn giá CP, thành tiền CP)
 *
 * Đầu ra:
 *  [
 *    'sections' => [
 *      [
 *        'key'    => 'NS',
 *        'letter' => 'A',
 *        'name'   => 'Nhân sự',
 *        'items'  => [
 *          [
 *            'chi_tiet_don_hang_id' => 123,
 *            'quote_cost_item_id'   => 456|null,
 *            'section_key'          => 'NS',
 *            'section_name'         => 'Nhân sự',
 *            'hang_muc'             => 'Âm thanh',
 *            'hang_muc_goc'         => 'Hệ thống âm thanh #2',
 *            'is_package'           => false,
 *            'chi_tiet'             => 'MC, loa, ...',
 *            'chi_tiet_html'        => '...',
 *            'dvt'                  => 'Bộ',
 *            'so_luong'             => 1,
 *            'sell_unit_price'      => 3000000,
 *            'sell_total_amount'    => 3000000,
 *            'supplier_id'          => 10|null,
 *            'supplier_name'        => 'Cty A',
 *            'cost_unit_price'      => 2200000,
 *            'cost_total_amount'    => 2200000,
 *            'note'                 => null,
 *          ],
 *          ...
 *        ],
 *        'total_cost' => ...,
 *        'total_sell' => ...,
 *      ],
 *      ...
 *    ],
 *    'totals' => [
 *      'total_cost'  => ...,
 *      'total_sell'  => ...,
 *      'margin'      => ...,
 *      'margin_rate' => ...,
 *    ],
 *  ]
 */
class QuoteCostEditorBuilder
{
    protected QuoteBuilder $quoteBuilder;

    public function __construct(QuoteBuilder $quoteBuilder)
    {
        $this->quoteBuilder = $quoteBuilder;
    }

    public function build(QuoteCost $cost): array
    {
        // Load đơn hàng đầy đủ quan hệ dùng cho QuoteBuilder
        $donHang = $cost->relationLoaded('donHang')
            ? $cost->donHang
            : $cost->donHang()
                ->with([
                    'chiTietDonHangs.sanPham.danhMuc',
                    'chiTietDonHangs.donViTinh',
                ])
                ->firstOrFail();

        // Dòng báo giá (từ DonHang) – source of truth cho layout
        $lines = $this->quoteBuilder->buildLinesForEditor($donHang);

        // Map items chi phí theo chi_tiet_don_hang_id
        $items = $cost->relationLoaded('items') ? $cost->items : $cost->items()->get();
        if (! $items instanceof Collection) {
            $items = collect($items);
        }
        $itemsByDetail = $items->keyBy('chi_tiet_don_hang_id');

        // Gom theo section_key NS/CSVC/TIEC/TD/CPK/KHAC
$sectionOrder = ['NS', 'CSVC', 'TIEC', 'TD', 'CPK', 'CPQL', 'CPFT', 'CPFG', 'GG', 'KHAC'];

        $letters      = range('A', 'Z');

        /** @var Collection $mapped */
        $mapped = $lines->map(function (array $line) use ($itemsByDetail) {
            $detailId = $line['chi_tiet_don_hang_id'] ?? null;
            $costItem = $detailId ? $itemsByDetail->get($detailId) : null;

            $qty       = (float) ($line['so_luong'] ?? 0);
            $sellUnit  = (int) ($line['don_gia'] ?? 0);
            $sellTotal = (int) ($line['thanh_tien'] ?? ($qty * $sellUnit));

            $costUnit = $costItem ? (int) ($costItem->cost_unit_price ?? 0) : 0;
            $costTotal = $costItem
                ? (int) ($costItem->cost_total_amount ?? $costItem->cost_total_computed)
                : 0;

            return [
                'chi_tiet_don_hang_id' => $detailId,
                'quote_cost_item_id'   => $costItem?->id,

                'section_key'          => $line['section_key'],
                'section_name'         => $line['section_name'] ?? $line['section_key'],

                'hang_muc'             => $line['hang_muc'],
                'hang_muc_goc'         => $line['hang_muc_goc'] ?? null,
                'is_package'           => (bool) ($line['is_package'] ?? false),

                'chi_tiet'             => $line['chi_tiet'],
                'chi_tiet_html'        => $line['chi_tiet_html'] ?? null,
                'dvt'                  => $line['dvt'],
                'so_luong'             => $qty,

                // Giá bán từ báo giá (khách thấy)
                'sell_unit_price'      => $sellUnit,
                'sell_total_amount'    => $sellTotal,

                // SUP / Chi phí (nội bộ)
                'supplier_id'          => $costItem?->supplier_id,
                'supplier_name'        => $costItem?->supplier_name,
                'cost_unit_price'      => $costUnit,
                'cost_total_amount'    => $costTotal,
                'note'                 => $costItem?->note,
            ];
        });

        $grouped = $mapped
            ->groupBy('section_key')
            ->sortBy(function ($items, $key) use ($sectionOrder) {
                $idx = array_search($key, $sectionOrder, true);
                return $idx === false ? 999 : $idx;
            });

        $sections   = [];
        $totalCost  = 0;
        $totalSell  = 0;
        $secIndex   = 0;

        foreach ($grouped as $code => $items) {
            if ($items->isEmpty()) {
                continue;
            }

            $letter = $letters[$secIndex] ?? '';
            $name   = (string) ($items->first()['section_name'] ?? $code);

            $sumCost = (int) $items->sum('cost_total_amount');
            $sumSell = (int) $items->sum('sell_total_amount');

            $totalCost += $sumCost;
            $totalSell += $sumSell;

            $sections[] = [
                'key'         => $code,
                'letter'      => $letter,
                'name'        => $name,
                'items'       => $items->values()->all(),
                'total_cost'  => $sumCost,
                'total_sell'  => $sumSell,
            ];

            $secIndex++;
        }

        $margin     = $totalSell - $totalCost;
        $marginRate = $totalSell > 0 ? round($margin * 100 / $totalSell, 2) : null;

        return [
            'sections' => $sections,
            'totals'   => [
                'total_cost'  => $totalCost,
                'total_sell'  => $totalSell,
                'margin'      => $margin,
                'margin_rate' => $marginRate,
            ],
        ];
    }
}
