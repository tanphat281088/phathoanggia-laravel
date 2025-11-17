<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPackageItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    // Cho phép fill mọi field (san_pham_id, item_id, so_luong, ...)
    protected $guarded = [];

    /**
     * Gói dịch vụ cha (san_phams.loai_san_pham = gói)
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'san_pham_id');
    }

    /**
     * Item con bên trong gói
     * - Hiện tại mình map đơn giản sang SanPham (thiết bị/dv lẻ)
     * - Sau này nếu cần phân biệt VT_ITEM / MON_AN... thì có thể tách thêm quan hệ khác
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'item_id');
    }
}
