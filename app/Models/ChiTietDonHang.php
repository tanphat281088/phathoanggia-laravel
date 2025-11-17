<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ChiTietDonHang extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $guarded = [];

    /**
     * Cast các field số / bool cho đúng kiểu
     */
    protected $casts = [
        'so_luong'              => 'int',
        'don_gia'               => 'int',
        'thanh_tien'            => 'int',
        'so_luong_da_xuat_kho'  => 'int',
        'base_cost'             => 'int',
        'cost_amount'           => 'int',
        'is_section_header'     => 'bool',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Giữ logic cũ: không lưu field giả "image" xuống DB
            unset($model->attributes['image']);
        });
    }

    /**
     * Số lượng còn lại có thể xuất kho cho chi tiết đơn hàng này.
     * (nếu sau này mày không dùng xuất kho nữa thì vẫn không sao, field vẫn tồn tại trong DB)
     */
    protected function soLuongConLaiXuatKho(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->so_luong - (int) $this->so_luong_da_xuat_kho,
        );
    }

    // ================= QUAN HỆ =================

    /**
     * Đơn hàng / Báo giá cha
     */
    public function donHang()
    {
        return $this->belongsTo(DonHang::class);
    }

    /**
     * Dịch vụ (SanPham) gắn với dòng này
     */
    public function sanPham()
    {
        return $this->belongsTo(SanPham::class);
    }

    /**
     * Đơn vị tính
     */
    public function donViTinh()
    {
        return $this->belongsTo(DonViTinh::class);
    }

    /**
     * Nhà cung cấp cho từng dòng (nếu có)
     * - dùng supplier_id (FK -> nha_cung_caps.id)
     */
    public function supplier()
    {
        return $this->belongsTo(NhaCungCap::class, 'supplier_id');
    }

    /**
     * Kết nối sẵn với bảng images để lưu ảnh
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // =============== HELPER ===============

    /**
     * Dòng này có phải là header nhóm (A/B/C/D) hay không?
     */
    public function isHeader(): bool
    {
        return (bool) $this->is_section_header;
    }

    /**
     * Dòng này có phải là dòng chi tiết dịch vụ (không phải header)?
     */
    public function isDetail(): bool
    {
        return ! $this->is_section_header;
    }
}
