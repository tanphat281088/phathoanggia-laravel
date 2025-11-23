<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteCost extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'quote_costs';

    // Cho phép fill toàn bộ, nếu sau này muốn siết lại thì chỉnh
    protected $guarded = [];

    /**
     * ====== LIÊN KẾT ======
     */

    /**
     * Báo giá gốc (don_hangs)
     */
    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    /**
     * Danh sách dòng chi phí (chi tiết)
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteCostItem::class, 'quote_cost_id');
    }

    /**
     * ====== HẰNG SỐ / MAP LABEL ======
     */

    // type: 1 = Đề xuất, 2 = Thực tế
    public const TYPE_DE_XUAT  = 1;
    public const TYPE_THUC_TE  = 2;

    public const TYPE_LABELS = [
        self::TYPE_DE_XUAT => 'Chi phí đề xuất',
        self::TYPE_THUC_TE => 'Chi phí thực tế',
    ];

    // status: 0 = Nháp, 1 = Đang chỉnh, 2 = Khoá
    public const STATUS_DRAFT     = 0;
    public const STATUS_EDITING   = 1;
    public const STATUS_LOCKED    = 2;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT   => 'Nháp',
        self::STATUS_EDITING => 'Đang chỉnh',
        self::STATUS_LOCKED  => 'Đã khoá',
    ];

    /**
     * ====== ACCESSORS ======
     */

    /**
     * Nhãn loại bảng chi phí (type_text)
     */
    public function getTypeTextAttribute(): string
    {
        $t = (int) ($this->type ?? 0);
        return self::TYPE_LABELS[$t] ?? 'Không rõ';
    }

    /**
     * Nhãn trạng thái (status_text)
     */
    public function getStatusTextAttribute(): string
    {
        $s = (int) ($this->status ?? 0);
        return self::STATUS_LABELS[$s] ?? 'Không rõ';
    }

    /**
     * % lợi nhuận (ưu tiên dùng margin_percent trong DB,
     * nếu null thì tính lại từ total_revenue & total_cost)
     */
    public function getMarginPercentComputedAttribute(): ?float
    {
        if ($this->margin_percent !== null) {
            return (float) $this->margin_percent;
        }

        $revenue = (int) ($this->total_revenue ?? 0);
        if ($revenue <= 0) {
            return null;
        }

        $cost   = (int) ($this->total_cost ?? 0);
        $margin = $revenue - $cost;

        return round($margin * 100 / $revenue, 2);
    }

    /**
     * ====== SCOPES ======
     */

    /**
     * Chỉ lấy bảng chi phí ĐỀ XUẤT
     */
    public function scopeDeXuat($query)
    {
        return $query->where('type', self::TYPE_DE_XUAT);
    }

    /**
     * Chỉ lấy bảng chi phí THỰC TẾ
     */
    public function scopeThucTe($query)
    {
        return $query->where('type', self::TYPE_THUC_TE);
    }
}
