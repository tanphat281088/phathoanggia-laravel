<?php

namespace App\Models;

use App\Traits\UserTrackable;
use App\Traits\UserNameResolver;
use App\Traits\DateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

class HopDongItem extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'hop_dong_items';

    // Cho phép fill toàn bộ, có thể siết lại sau nếu muốn
    protected $guarded = [];

    protected $casts = [
        'so_luong'   => 'float',
        'don_gia'    => 'integer',
        'thanh_tien' => 'integer',
        'is_package' => 'boolean',
    ];

    /**
     * Hợp đồng cha (header)
     */
    public function hopDong()
    {
        return $this->belongsTo(HopDong::class, 'hop_dong_id');
    }

    /**
     * Dòng chi tiết báo giá gốc (nếu còn tồn tại)
     */
    public function chiTietDonHang()
    {
        return $this->belongsTo(ChiTietDonHang::class, 'chi_tiet_don_hang_id');
    }
}
