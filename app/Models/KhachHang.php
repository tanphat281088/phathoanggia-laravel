<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mô hình Khách hàng cho ERP Sự kiện Phát Hoàng Gia.
 *
 * Phân loại:
 * - customer_type:
 *      0 = Event client    (khách sự kiện, doanh nghiệp / brand)
 *      1 = Wedding client  (khách tiệc cưới)
 *      2 = Agency client   (khách sỉ / agency / planner / booking)
 *
 * - is_system_customer:
 *      1 = Khách hệ thống (có hồ sơ đầy đủ, dùng lâu dài)
 *      0 = Khách vãng lai  (tạo nhanh trên báo giá, có thể nâng cấp sau)
 */
class KhachHang extends Model
{
    use HasFactory;

    protected $table = 'khach_hangs';

    // ===== Hằng số loại khách chuyên ngành Sự kiện =====
    public const TYPE_EVENT   = 0;
    public const TYPE_WEDDING = 1;
    public const TYPE_AGENCY  = 2;

    // ===== Cho phép fill các cột cần thiết =====
    protected $fillable = [
        'ma_kh',
        'ten_khach_hang',
        'email',
        'so_dien_thoai',
        'dia_chi',

        'loai_khach_hang_id',   // hạng thành viên (Regular/VIP/...)
        'customer_type',         // 0=Event,1=Wedding,2=Agency
        'is_system_customer',    // 1=Hệ thống, 0=Vãng lai

        // B2B (Event / Agency)
        'company_name',
        'tax_code',
        'department',
        'position',
        'industry',

        // Wedding
        'bride_name',
        'groom_name',
        'wedding_date',
        'wedding_venue',

        // CRM
        'kenh_lien_he',
        'source_detail',
        'ghi_chu',
        'note_internal',

        // Tài chính
        'cong_no',
        'doanh_thu_tich_luy',

        'trang_thai',
        'nguoi_tao',
        'nguoi_cap_nhat',
    ];

    // ===== Cast kiểu dữ liệu =====
    protected $casts = [
        'customer_type'      => 'integer',
        'is_system_customer' => 'boolean',
        'wedding_date'       => 'date',
        'cong_no'            => 'integer',
        'doanh_thu_tich_luy' => 'integer',
        'trang_thai'         => 'integer',
    ];

    // =====================================================================
    //  QUAN HỆ
    // =====================================================================

    /**
     * Hạng khách hàng (Regular / VIP / ...)
     */
    public function loaiKhachHang()
    {
        return $this->belongsTo(LoaiKhachHang::class, 'loai_khach_hang_id');
    }

    /**
     * Các báo giá / đơn hàng (sau này ta sẽ dùng don_hangs như "báo giá sự kiện").
     */
    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'khach_hang_id');
    }

    /**
     * Các sự kiện biến động điểm (nếu bạn dùng loyalty cho event/wedding).
     */
    public function pointEvents()
    {
        return $this->hasMany(KhachHangPointEvent::class, 'khach_hang_id');
    }

    // =====================================================================
    //  SCOPES LỌC THEO LOẠI KHÁCH & LEVEL
    // =====================================================================

    /**
     * Chỉ khách Event (doanh nghiệp / brand).
     */
    public function scopeEvent($query)
    {
        return $query->where('customer_type', self::TYPE_EVENT);
    }

    /**
     * Chỉ khách Wedding.
     */
    public function scopeWedding($query)
    {
        return $query->where('customer_type', self::TYPE_WEDDING);
    }

    /**
     * Chỉ khách Agency (khách sỉ, planner, booking).
     */
    public function scopeAgency($query)
    {
        return $query->where('customer_type', self::TYPE_AGENCY);
    }

    /**
     * Chỉ khách hệ thống.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_customer', true);
    }

    /**
     * Chỉ khách vãng lai.
     */
    public function scopeWalkIn($query)
    {
        return $query->where('is_system_customer', false);
    }

    // =====================================================================
    //  ACCESSORS / HELPER
    // =====================================================================

    /**
     * Tên hiển thị thân thiện cho UI:
     * - Event/Agency: ưu tiên company_name, fallback ten_khach_hang
     * - Wedding: ưu tiên "Cô dâu - Chú rể" nếu có.
     */
    public function getDisplayNameAttribute(): string
    {
        // Wedding: ưu tiên cô dâu - chú rể
        if ($this->customer_type === self::TYPE_WEDDING) {
            $bride  = trim((string) $this->bride_name);
            $groom  = trim((string) $this->groom_name);
            $parts  = array_filter([$bride, $groom]);

            if (! empty($parts)) {
                return implode(' - ', $parts);
            }
        }

        // Event / Agency: ưu tiên company_name
        $company = trim((string) $this->company_name);
        if ($company !== '') {
            return $company;
        }

        // Fallback: ten_khach_hang
        return (string) $this->ten_khach_hang;
    }

    /**
     * Helper: có phải khách Event không?
     */
    public function isEvent(): bool
    {
        return $this->customer_type === self::TYPE_EVENT;
    }

    /**
     * Helper: có phải khách Wedding không?
     */
    public function isWedding(): bool
    {
        return $this->customer_type === self::TYPE_WEDDING;
    }

    /**
     * Helper: có phải khách Agency không?
     */
    public function isAgency(): bool
    {
        return $this->customer_type === self::TYPE_AGENCY;
    }

    /**
     * Helper: có phải khách hệ thống không?
     */
    public function isSystemCustomer(): bool
    {
        return (bool) $this->is_system_customer;
    }

    /**
     * Helper: có phải khách vãng lai không?
     */
    public function isWalkIn(): bool
    {
        return ! $this->is_system_customer;
    }
}
