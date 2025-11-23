<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoiDichVuItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'goi_dich_vu_items';

    // Cho phép fill toàn bộ, có thể siết lại nếu muốn
    protected $guarded = [];

    /**
     * Gói dịch vụ cha (tầng 3).
     *
     * Ví dụ:
     *  - Gói: "Gói âm thanh 100 khách - Basic"
     *    -> Item (this): Loa EV 50 x 2, Mixer MG16XU x 1...
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(GoiDichVu::class, 'goi_dich_vu_id');
    }

    /**
     * Chi tiết dịch vụ / sản phẩm (từ module Sản phẩm).
     *
     * Ví dụ:
     *  - san_phams: Loa EV 50, Đèn moving head, ...
     */
    public function sanPham(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'san_pham_id');
    }
}
