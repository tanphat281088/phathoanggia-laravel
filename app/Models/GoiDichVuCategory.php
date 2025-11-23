<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoiDichVuCategory extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'goi_dich_vu_categories';

    // Cho phép fill toàn bộ, có thể siết lại nếu muốn
    protected $guarded = [];

    /**
     * Nhóm DANH MỤC GÓI DỊCH VỤ (tầng 1)
     *
     * Ví dụ:
     *  - Group: "Gói sự kiện âm thanh"
     *      -> Category (this): "Gói âm thanh tiệc cưới"
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GoiDichVuGroup::class, 'group_id');
    }

    /**
     * Danh sách GÓI DỊCH VỤ (tầng 3) thuộc nhóm này.
     *
     * Ví dụ:
     *  - Category: "Gói âm thanh tiệc cưới"
     *      -> "Gói âm thanh 100 khách - Basic"
     *      -> "Gói âm thanh 100 khách - Premium"
     */
    public function packages(): HasMany
    {
        return $this->hasMany(GoiDichVu::class, 'category_id');
    }
}
