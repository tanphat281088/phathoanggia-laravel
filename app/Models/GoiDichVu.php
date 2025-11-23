<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoiDichVu extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'goi_dich_vus';

    // Cho phép fill toàn bộ, có thể siết lại nếu muốn
    protected $guarded = [];

    /**
     * Nhóm gói dịch vụ (tầng 2) mà gói này thuộc về.
     *
     * Ví dụ:
     *  - Category: "Gói âm thanh tiệc cưới"
     *      -> Package (this): "Gói âm thanh 100 khách - Basic"
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GoiDichVuCategory::class, 'category_id');
    }

    /**
     * Danh sách chi tiết (item) nằm trong gói này.
     *
     * Mỗi item nối tới 1 CHI TIẾT DỊCH VỤ (san_phams) + số lượng, đơn giá...
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoiDichVuItem::class, 'goi_dich_vu_id');
    }
}
