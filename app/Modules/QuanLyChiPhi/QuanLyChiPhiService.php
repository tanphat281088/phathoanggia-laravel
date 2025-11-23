<?php

namespace App\Modules\QuanLyChiPhi;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\DonHang;
use App\Models\QuoteCost;
use App\Models\QuoteCostItem;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuanLyChiPhiService
{
    /**
     * Lấy danh sách bảng chi phí theo TYPE (1 = Đề xuất, 2 = Thực tế)
     * - Dùng FilterWithPagination giống các module khác
     * - Có thể filter theo:
     *      + ma_don_hang, ten_khach_hang (thông qua FilterWithPagination nếu join sau này)
     *      + status, margin, ...
     */
    public function getAll(array $params = [], int $type = QuoteCost::TYPE_DE_XUAT)
    {
        try {
            // Base query: load luôn báo giá gốc để FE hiển thị
            $query = QuoteCost::query()
                ->with(['donHang'])
                ->where('type', $type);

            // Dùng FilterWithPagination như các module khác
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['quote_costs.*']
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page'  => $result['current_page'],
                    'last_page'     => $result['last_page'],
                    'from'          => $result['from'],
                    'to'            => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách bảng chi phí: ' . $e->getMessage());
        }
    }

    /**
     * Lấy chi tiết 1 bảng chi phí theo ID
     * - Kèm báo giá gốc + các dòng chi phí
     * - Nếu truyền type thì kiểm tra khớp (đề xuất/thực tế)
     */
    public function getById(int $id, ?int $type = null)
    {
        $query = QuoteCost::with([
            'donHang',
            'items.chiTietDonHang',
            'items.supplier',
        ])->whereKey($id);

        if ($type !== null) {
            $query->where('type', $type);
        }

        $cost = $query->first();

        if (! $cost) {
            return CustomResponse::error('Bảng chi phí không tồn tại');
        }

        return $cost;
    }

    /**
     * Tạo (hoặc lấy lại) bảng chi phí cho 1 báo giá
     * - type: 1 = Đề xuất, 2 = Thực tế
     * - Nếu đã tồn tại (don_hang_id, type) → trả về record cũ
     * - Nếu chưa: tạo mới, snapshot tổng doanh thu từ đơn hàng
     * - Giai đoạn 1: KHÔNG auto-fill items, để anh nhập tay (an toàn)
     */
    public function createFromDonHang(int $donHangId, int $type)
    {
        try {
            return DB::transaction(function () use ($donHangId, $type) {
                // Nếu đã có bảng chi phí cùng loại cho báo giá này → trả về luôn
                $existing = QuoteCost::where('don_hang_id', $donHangId)
                    ->where('type', $type)
                    ->first();

                if ($existing) {
                    return $existing->load('donHang');
                }

                /** @var \App\Models\DonHang|null $donHang */
                $donHang = DonHang::find($donHangId);
                if (! $donHang) {
                    return CustomResponse::error('Báo giá không tồn tại (don_hang_id không hợp lệ)');
                }

                // Snapshot tổng doanh thu từ đơn (ưu tiên tong_tien_can_thanh_toan)
                $totalRevenue = (int) ($donHang->tong_tien_can_thanh_toan ?? 0);

                // Giai đoạn 1: tạo bảng rỗng, total_cost = 0, margin = revenue
                $userId = Auth::id();
                $userIdStr = $userId !== null ? (string) $userId : null;

                $cost = QuoteCost::create([
                    'don_hang_id'     => $donHang->id,
                    'type'            => $type,
                    'code'            => null,        // có thể generate sau (CPDX..., CPTT...)
                    'status'          => QuoteCost::STATUS_DRAFT,
                    'total_revenue'   => $totalRevenue,
                    'total_cost'      => 0,
                    'total_margin'    => $totalRevenue,
                    'margin_percent'  => $totalRevenue > 0 ? 100.0 : null,
                    'note'            => null,
                    'nguoi_tao'       => $userIdStr,
                    'nguoi_cap_nhat'  => $userIdStr,
                ]);

                return $cost->load('donHang');
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi tạo bảng chi phí: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật 1 bảng chi phí + sync danh sách dòng chi phí
     *
     * $data:
     *  - Các field header (status, note, total_revenue nếu muốn override)
     *  - items: [
     *      [
     *          'id'                    => (tùy chọn, giai đoạn 1 có thể bỏ qua, luôn create mới),
     *          'chi_tiet_don_hang_id'  => nullable,
     *          'hang_muc_goc',
     *          'section_code',
     *          'line_no',
     *          'description',
     *          'dvt',
     *          'qty',
     *          'supplier_id',
     *          'supplier_name',
     *          'cost_unit_price',
     *          'cost_total_amount',
     *          'sell_unit_price',
     *          'sell_total_amount',
     *          'note',
     *      ],
     *    ]
     */
public function update(int $id, array $data)
{
    try {
        return DB::transaction(function () use ($id, $data) {
            /** @var \App\Models\QuoteCost $cost */
            $cost = QuoteCost::findOrFail($id);

            // ===== LỌC & DỌN DỮ LIỆU TỪ FE =====
            // Không cho sửa các field sau:
            //  - id, don_hang_id, type (loại bảng chi phí)
            //  - don_hang / donHang (object báo giá gốc)
            //  - created_at / updated_at (để Eloquent tự quản)
            unset(
                $data['id'],
                $data['don_hang_id'],
                $data['type'],
                $data['don_hang'],
                $data['donHang'],
                $data['created_at'],
                $data['updated_at']
            );

            // Tách items ra khỏi header
            $items = $data['items'] ?? null;
            unset($data['items']);

            // Nếu FE có cho phép chỉnh total_revenue: dùng giá trị mới, ngược lại giữ cũ
            $totalRevenue = array_key_exists('total_revenue', $data)
                ? (int) $data['total_revenue']
                : (int) ($cost->total_revenue ?? 0);

            // Cập nhật header trước (chưa động đến total_cost/margin)
            $data['total_revenue']  = $totalRevenue;
            $data['nguoi_cap_nhat'] = (string) (Auth::id() ?? $cost->nguoi_cap_nhat);

            // Nếu có status gửi lên thì ép về int 0|1|2 cho chắc
            if (array_key_exists('status', $data)) {
                $s = (int) $data['status'];
                $data['status'] = in_array($s, [
                    QuoteCost::STATUS_DRAFT,
                    QuoteCost::STATUS_EDITING,
                    QuoteCost::STATUS_LOCKED,
                ], true) ? $s : QuoteCost::STATUS_DRAFT;
            }

            // Ghi header (chỉ với field đã dọn)
            $cost->update($data);

            // ===== SYNC DANH SÁCH DÒNG CHI PHÍ =====
            $totalCost = 0;
            $totalSell = 0;

            if (is_array($items)) {
                // Xoá hết dòng cũ, ghi lại mới (simple & an toàn)
                QuoteCostItem::where('quote_cost_id', $cost->id)->delete();

                foreach ($items as $row) {
                    // Bỏ dòng hoàn toàn rỗng
                    $hasSomething =
                        !empty($row['description'] ?? null) ||
                        !empty($row['qty'] ?? null) ||
                        !empty($row['cost_unit_price'] ?? null) ||
                        !empty($row['sell_unit_price'] ?? null);
                    if (! $hasSomething) {
                        continue;
                    }

                    $qty      = isset($row['qty']) ? (float) $row['qty'] : 0.0;
                    $costUnit = isset($row['cost_unit_price']) ? (int) $row['cost_unit_price'] : 0;
                    $sellUnit = isset($row['sell_unit_price']) ? (int) $row['sell_unit_price'] : 0;

                    $costTotal = isset($row['cost_total_amount'])
                        ? (int) $row['cost_total_amount']
                        : (int) round($qty * $costUnit);

                    $sellTotal = isset($row['sell_total_amount'])
                        ? (int) $row['sell_total_amount']
                        : (int) round($qty * $sellUnit);

                    $item = QuoteCostItem::create([
                        'quote_cost_id'         => $cost->id,
                        'chi_tiet_don_hang_id'  => $row['chi_tiet_don_hang_id'] ?? null,
                        'hang_muc_goc'          => $row['hang_muc_goc'] ?? null,
                        'section_code'          => $row['section_code'] ?? null,
                        'line_no'               => isset($row['line_no']) ? (int) $row['line_no'] : 0,
                        'description'           => $row['description'] ?? null,
                        'dvt'                   => $row['dvt'] ?? null,
                        'qty'                   => $qty,
                        'supplier_id'           => $row['supplier_id'] ?? null,
                        'supplier_name'         => $row['supplier_name'] ?? null,
                        'cost_unit_price'       => $costUnit,
                        'cost_total_amount'     => $costTotal,
                        'sell_unit_price'       => $sellUnit,
                        'sell_total_amount'     => $sellTotal,
                        'note'                  => $row['note'] ?? null,
                        'nguoi_tao'             => (string) (Auth::id() ?? $cost->nguoi_tao),
                        'nguoi_cap_nhat'        => (string) (Auth::id() ?? $cost->nguoi_cap_nhat),
                    ]);

                    $totalCost += $item->cost_total_computed;
                    $totalSell += $item->sell_total_computed;
                }
            } else {
                // Không gửi items -> giữ tổng chi phí cũ
                $totalCost = (int) ($cost->total_cost ?? 0);
                $totalSell = $totalRevenue;
            }

            // ===== TÍNH LẠI MARGIN & % =====
            $margin        = $totalRevenue - $totalCost;
            $marginPercent = $totalRevenue > 0
                ? round($margin * 100 / $totalRevenue, 2)
                : null;

            $cost->update([
                'total_revenue'  => $totalRevenue,
                'total_cost'     => $totalCost,
                'total_margin'   => $margin,
                'margin_percent' => $marginPercent,
            ]);

            return $cost->fresh(['donHang', 'items']);
        });
    } catch (Exception $e) {
        return CustomResponse::error('Lỗi khi cập nhật bảng chi phí: ' . $e->getMessage());
    }
}


    /**
     * Xoá bảng chi phí (và toàn bộ dòng chi phí)
     */
    public function delete(int $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $cost = QuoteCost::findOrFail($id);

                // Xoá items (có onDelete('cascade') nhưng xoá tay cho rõ)
                QuoteCostItem::where('quote_cost_id', $cost->id)->delete();

                $cost->delete();

                return true;
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xoá bảng chi phí: ' . $e->getMessage());
        }
    }

    /**
     * Từ bảng chi phí ĐỀ XUẤT → tạo (hoặc mở lại) bảng chi phí THỰC TẾ tương ứng
     * - Nếu đã có bảng thực tế: trả về bảng thực tế hiện tại (không clone nữa)
     * - Nếu chưa:
     *      + Tạo header type = THUC_TE
     *      + Clone toàn bộ items: cost_* & sell_* giữ nguyên
     */
    public function transferToActual(int $deXuatId)
    {
        try {
            return DB::transaction(function () use ($deXuatId) {
                /** @var \App\Models\QuoteCost $deXuat */
                $deXuat = QuoteCost::with('items')->findOrFail($deXuatId);

                if ((int) $deXuat->type !== QuoteCost::TYPE_DE_XUAT) {
                    return CustomResponse::error('Chỉ được chuyển các bảng "Chi phí đề xuất" sang Thực tế');
                }

                // Kiểm tra đã có bảng THỰC TẾ cho cùng báo giá chưa
                $existingActual = QuoteCost::where('don_hang_id', $deXuat->don_hang_id)
                    ->where('type', QuoteCost::TYPE_THUC_TE)
                    ->first();

                if ($existingActual) {
                    // Trả về bản thực tế hiện tại (không clone lại)
                    return $existingActual->load(['donHang', 'items']);
                }

                $userId = (string) (Auth::id() ?? $deXuat->nguoi_tao);

                // Tạo header thực tế: copy tổng doanh thu & chi phí đề xuất làm default
                $actual = QuoteCost::create([
                    'don_hang_id'     => $deXuat->don_hang_id,
                    'type'            => QuoteCost::TYPE_THUC_TE,
                    'code'            => null,
                    'status'          => QuoteCost::STATUS_DRAFT,
                    'total_revenue'   => (int) ($deXuat->total_revenue ?? 0),
                    'total_cost'      => (int) ($deXuat->total_cost ?? 0),
                    'total_margin'    => (int) ($deXuat->total_margin ?? 0),
                    'margin_percent'  => $deXuat->margin_percent,
                    'note'            => null,
                    'nguoi_tao'       => $userId,
                    'nguoi_cap_nhat'  => $userId,
                ]);

                $totalCost = 0;
                $totalSell = 0;

                foreach ($deXuat->items as $item) {
                    $newItem = QuoteCostItem::create([
                        'quote_cost_id'         => $actual->id,
                        'chi_tiet_don_hang_id'  => $item->chi_tiet_don_hang_id,
                        'hang_muc_goc'          => $item->hang_muc_goc,
                        'section_code'          => $item->section_code,
                        'line_no'               => $item->line_no,
                        'description'           => $item->description,
                        'dvt'                   => $item->dvt,
                        'qty'                   => $item->qty,
                        'supplier_id'           => $item->supplier_id,
                        'supplier_name'         => $item->supplier_name,
                        'cost_unit_price'       => $item->cost_unit_price,
                        'cost_total_amount'     => $item->cost_total_amount,
                        'sell_unit_price'       => $item->sell_unit_price,
                        'sell_total_amount'     => $item->sell_total_amount,
                        'note'                  => $item->note,
                        'nguoi_tao'             => $userId,
                        'nguoi_cap_nhat'        => $userId,
                    ]);

                    $totalCost += $newItem->cost_total_computed;
                    $totalSell += $newItem->sell_total_computed;
                }

                // Cập nhật lại tổng trên header thực tế
                $margin = $totalSell - $totalCost;
                $marginPercent = $totalSell > 0 ? round($margin * 100 / $totalSell, 2) : null;

                $actual->update([
                    'total_revenue'  => $totalSell,   // thực tế: dùng tổng sell từ items
                    'total_cost'     => $totalCost,
                    'total_margin'   => $margin,
                    'margin_percent' => $marginPercent,
                ]);

                return $actual->fresh(['donHang', 'items']);
            });
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi chuyển chi phí Đề xuất sang Thực tế: ' . $e->getMessage());
        }
    }
}
