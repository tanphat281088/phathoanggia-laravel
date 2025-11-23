<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteCostItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'quote_cost_items';

    // Cho phép fill toàn bộ; nếu sau này muốn siết lại thì chỉnh
    protected $guarded = [];

    /**
     * ====== LIÊN KẾT ======
     */

    /**
     * Header chi phí (Đề xuất / Thực tế)
     */
    public function quoteCost(): BelongsTo
    {
        return $this->belongsTo(QuoteCost::class, 'quote_cost_id');
    }

    /**
     * Dòng báo giá gốc (nếu có map)
     */
    public function chiTietDonHang(): BelongsTo
    {
        return $this->belongsTo(ChiTietDonHang::class, 'chi_tiet_don_hang_id');
    }

    /**
     * Nhà cung cấp (nếu chọn từ master)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(NhaCungCap::class, 'supplier_id');
    }

    /**
     * ====== ACCESSORS HỖ TRỢ TÍNH TOÁN ======
     */

    /**
     * Thành tiền chi phí đã tính (ưu tiên cost_total_amount, nếu =0 thì tự tính từ qty * cost_unit_price)
     */
    public function getCostTotalComputedAttribute(): int
    {
        $stored = (int) ($this->cost_total_amount ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $qty   = (float) ($this->qty ?? 0);
        $price = (int) ($this->cost_unit_price ?? 0);

        return (int) round($qty * $price);
    }

    /**
     * Thành tiền bán đã tính (ưu tiên sell_total_amount, nếu =0 thì tự tính từ qty * sell_unit_price)
     */
    public function getSellTotalComputedAttribute(): int
    {
        $stored = (int) ($this->sell_total_amount ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $qty   = (float) ($this->qty ?? 0);
        $price = (int) ($this->sell_unit_price ?? 0);

        return (int) round($qty * $price);
    }

    /**
     * Lợi nhuận dòng này = doanh thu - chi phí (computed)
     */
    public function getLineMarginAttribute(): int
    {
        return $this->sell_total_computed - $this->cost_total_computed;
    }
}
