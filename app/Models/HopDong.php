<?php

namespace App\Models;

use App\Traits\UserTrackable;
use App\Traits\UserNameResolver;
use App\Traits\DateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HopDong extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $table = 'hop_dongs';
    protected $guarded = [];

    /**
     * CÁC TRẠNG THÁI HỢP ĐỒNG
     * 0 = Nháp
     * 1 = Đã gửi khách
     * 2 = Khách duyệt / Đã ký
     * 3 = Đã thanh lý
     * 4 = Hủy
     */
    public const STATUS_DRAFT       = 0;
    public const STATUS_SENT        = 1;
    public const STATUS_APPROVED    = 2;
    public const STATUS_LIQUIDATED  = 3;
    public const STATUS_CANCELLED   = 4;

    public const STATUS_LABELS = [
        0 => 'Nháp',
        1 => 'Đã gửi khách',
        2 => 'Đã ký',
        3 => 'Đã thanh lý',
        4 => 'Đã hủy',
    ];

    protected $casts = [
        'ngay_hop_dong'   => 'datetime',
        'ngay_hieu_luc'   => 'datetime',
        'ngay_thanh_ly'   => 'datetime',
        'status'          => 'integer',

        // JSON cũ
        'dieu_khoan'      => 'array',       // JSON lưu điều khoản
        'dot_thanh_toan'  => 'array',       // JSON lưu đợt thanh toán (30/70…)
        'thong_tin_khach' => 'array',       // Snapshot thông tin KH để cố định file HĐ

        // JSON mới: toàn bộ nội dung hợp đồng theo Level 2
        'body_json'       => 'array',
    ];

    protected $appends = [
        'tong_tien',
        'tong_so_luong',
    ];

    /** ===========================
     * QUAN HỆ
     * =========================== */
    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    public function items()
    {
        return $this->hasMany(HopDongItem::class, 'hop_dong_id');
    }

    /** ===========================
     * ACCESSORS
     * =========================== */

    // Tổng tiền HĐ = sum(thanh_tien)
    public function getTongTienAttribute(): int
    {
        return $this->items->sum(function ($it) {
            return (int) ($it->thanh_tien ?? 0);
        });
    }

    // Tổng số lượng
    public function getTongSoLuongAttribute(): int
    {
        return $this->items->sum(function ($it) {
            return (int) ($it->so_luong ?? 0);
        });
    }

    // Trạng thái text
    public function getStatusTextAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Không rõ';
    }

    /** ===========================
     * HOOK
     * =========================== */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (HopDong $model) {
            // Auto generate SO HỢP ĐỒNG nếu chưa có:
            // dạng: SỐ/YYYY/HĐDV/PHG  (vd: 393/2025/HĐDV/PHG)
            if (empty($model->so_hop_dong)) {
                // Xác định năm: ưu tiên ngay_hop_dong, nếu không có thì lấy năm hiện tại
                if ($model->ngay_hop_dong instanceof Carbon) {
                    $year = (int) $model->ngay_hop_dong->format('Y');
                } elseif (! empty($model->ngay_hop_dong)) {
                    $year = (int) Carbon::parse($model->ngay_hop_dong)->format('Y');
                } else {
                    $year = (int) now()->format('Y');
                }

          $suffix = '/' . $year . '/HĐDV/PHG';

// Tìm số thứ tự lớn nhất của năm đó (phần trước dấu "/")
// Dùng DB::table để tránh các select phụ / global scope của Eloquent
$maxNumber = DB::table('hop_dongs')
    ->where('so_hop_dong', 'like', '%' . $suffix)
    ->max(DB::raw("CAST(SUBSTRING_INDEX(so_hop_dong, '/', 1) AS UNSIGNED)"));

// Nếu chưa có HĐ nào trong năm đó → bắt đầu từ 1
$next = $maxNumber ? ((int) $maxNumber + 1) : 1;

$model->so_hop_dong = $next . $suffix;

            }

            // Không lưu field giả vào DB (nếu FE đẩy lên)
            unset($model->attributes['file_preview']);
        });
    }
}
