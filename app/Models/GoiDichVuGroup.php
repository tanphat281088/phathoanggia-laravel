<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoiDichVuGroup extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'goi_dich_vu_groups';

    // Cho phép fill toàn bộ, bạn có thể siết lại sau nếu muốn
    protected $guarded = [];

    /**
     * Nhóm gói dịch vụ (tầng 2) trực thuộc nhóm này.
     *
     * Ví dụ:
     *  - Group: "Gói sự kiện âm thanh"
     *      -> Category: "Gói âm thanh tiệc cưới"
     *      -> Category: "Gói âm thanh hội nghị"
     */
    public function categories(): HasMany
    {
        return $this->hasMany(GoiDichVuCategory::class, 'group_id');
    }
}
