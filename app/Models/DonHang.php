<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DonHang extends Model
{
    use DateTimeFormatter, UserNameResolver, UserTrackable;

    /**
     * ====== Trạng thái GIAO HÀNG (mapping cũ, giữ để không phá module cũ) ======
     * 0 = Chưa giao, 1 = Đang giao, 2 = Đã giao, 3 = Đã hủy
     */
    public const TRANG_THAI_CHUA_GIAO = 0;
    public const TRANG_THAI_DANG_GIAO = 1;
    public const TRANG_THAI_DA_GIAO   = 2;
    public const TRANG_THAI_DA_HUY    = 3;

    /** Map nhãn trạng thái dùng chung trong BE */
    public const STATUS_LABELS = [
        self::TRANG_THAI_CHUA_GIAO => 'Chưa giao',
        self::TRANG_THAI_DANG_GIAO => 'Đang giao',
        self::TRANG_THAI_DA_GIAO   => 'Đã giao',
        self::TRANG_THAI_DA_HUY    => 'Đã hủy',
    ];

    /**
     * ====== Trạng thái BÁO GIÁ SỰ KIỆN (dùng cột quote_status) ======
     * 0 = Nháp
     * 1 = Đã gửi khách
     * 2 = Đang thương lượng / chỉnh sửa
     * 3 = Khách đã duyệt (sẽ sinh Sự kiện)
     * 4 = Đã thực hiện (event đã diễn ra)
     * 5 = Đã tất toán (tài chính xong)
     * 6 = Đã huỷ
     */
    public const QUOTE_STATUS_DRAFT       = 0;
    public const QUOTE_STATUS_SENT        = 1;
    public const QUOTE_STATUS_NEGOTIATING = 2;
    public const QUOTE_STATUS_APPROVED    = 3;
    public const QUOTE_STATUS_DONE        = 4;
    public const QUOTE_STATUS_SETTLED     = 5;
    public const QUOTE_STATUS_CANCELLED   = 6;

    public const QUOTE_STATUS_LABELS = [
        self::QUOTE_STATUS_DRAFT       => 'Nháp',
        self::QUOTE_STATUS_SENT        => 'Đã gửi',
        self::QUOTE_STATUS_NEGOTIATING => 'Thương lượng',
        self::QUOTE_STATUS_APPROVED    => 'Khách duyệt',
        self::QUOTE_STATUS_DONE        => 'Đã thực hiện',
        self::QUOTE_STATUS_SETTLED     => 'Đã tất toán',
        self::QUOTE_STATUS_CANCELLED   => 'Đã hủy',
    ];

    protected $guarded = [];

    /**
     * Append thuộc tính dẫn xuất vào JSON/API.
     */
    protected $appends = [
        'so_tien_con_lai',
        // Nếu sau này anh muốn FE nhận thêm: 'quote_status_text', 'event_date_range'
        // có thể thêm vào đây mà không cần đổi logic khác.
    ];

    /**
     * Casts: giữ nguyên phần giờ của lịch giao; trạng thái là int.
     * Bổ sung cast cho event_start/event_end, guest_count, quote_status, member_discount_*.
     */
    protected $casts = [
        'nguoi_nhan_thoi_gian'   => 'datetime',
        'trang_thai_don_hang'    => 'integer',

        // BÁO GIÁ SỰ KIỆN
        'event_start'            => 'datetime',
        'event_end'              => 'datetime',
        'guest_count'            => 'integer',
        'quote_status'           => 'integer',

        // Giảm giá thành viên
        'member_discount_percent' => 'integer',
        'member_discount_amount'  => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Giữ logic cũ: không lưu field giả "image" xuống DB
            unset($model->attributes['image']);
        });
    }

    // =====================================================================
    //  QUAN HỆ
    // =====================================================================

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class);
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class);
    }

    public function nguoiTao()
    {
        return $this->belongsTo(User::class, 'nguoi_tao');
    }

    public function phieuThu()
    {
        return $this->hasMany(PhieuThu::class);
    }

    public function chiTietPhieuThu()
    {
        return $this->hasMany(ChiTietPhieuThu::class);
    }

    // =====================================================================
    //  ACCESSORS / DERIVED FIELDS
    // =====================================================================

    /**
     * Số tiền còn lại = max(0, cần thanh toán - đã thanh toán)
     */
    public function getSoTienConLaiAttribute(): int
    {
        $tong  = (int) ($this->tong_tien_can_thanh_toan ?? 0);
        $daTT  = (int) ($this->so_tien_da_thanh_toan ?? 0);
        $remain = $tong - $daTT;
        return $remain > 0 ? $remain : 0;
    }

    /**
     * Helper: trả về nhãn trạng thái GIAO HÀNG theo value.
     */
    public static function labelTrangThai(int $value): string
    {
        return self::STATUS_LABELS[$value] ?? 'Không rõ';
    }

    /**
     * Accessor: nhãn trạng thái giao hàng (nếu FE cần).
     */
    public function getTrangThaiTextAttribute(): string
    {
        return self::labelTrangThai((int) $this->trang_thai_don_hang);
    }

    /**
     * Helper: nhãn trạng thái báo giá sự kiện theo quote_status.
     */
    public static function labelQuoteStatus(?int $value): string
    {
        if ($value === null) {
            return self::QUOTE_STATUS_LABELS[self::QUOTE_STATUS_DRAFT];
        }
        return self::QUOTE_STATUS_LABELS[$value] ?? 'Không rõ';
    }

    /**
     * Accessor: nhãn trạng thái báo giá (quote_status_text).
     */
    public function getQuoteStatusTextAttribute(): string
    {
        return self::labelQuoteStatus($this->quote_status);
    }

    /**
     * Accessor (tuỳ chọn): chuỗi mô tả khoảng thời gian sự kiện.
     * VD: "10/12/2025 18:00 - 10/12/2025 22:00"
     */
    public function getEventDateRangeAttribute(): ?string
    {
        if (! $this->event_start) {
            return null;
        }
        $start = $this->event_start instanceof Carbon
            ? $this->event_start
            : Carbon::parse($this->event_start);

        if (! $this->event_end) {
            return $start->format('d/m/Y H:i');
        }

        $end = $this->event_end instanceof Carbon
            ? $this->event_end
            : Carbon::parse($this->event_end);

        return $start->format('d/m/Y H:i') . ' - ' . $end->format('d/m/Y H:i');
    }

    // =====================================================================
    //  SCOPES HỖ TRỢ GIAO HÀNG (giữ nguyên cho tương thích)
    // =====================================================================

    /**
     * Lọc theo trạng thái GIAO HÀNG (0/1/2/3). Bỏ qua nếu null.
     */
    public function scopeTrangThai($query, ?int $status)
    {
        if ($status === null) return $query;
        return $query->where('trang_thai_don_hang', $status);
    }

    /**
     * Lọc đơn giao TRONG NGÀY (theo ngày hệ thống).
     */
    public function scopeGiaoTrongNgay($query, ?Carbon $day = null)
    {
        $day = ($day ?? Carbon::today());
        return $query->whereDate('nguoi_nhan_thoi_gian', $day->toDateString());
    }

    /**
     * Lọc theo khoảng thời gian giao (datetime).
     */
    public function scopeGiaoTuDen($query, ?Carbon $from = null, ?Carbon $to = null)
    {
        if ($from) $query->where('nguoi_nhan_thoi_gian', '>=', $from);
        if ($to)   $query->where('nguoi_nhan_thoi_gian', '<=', $to);
        return $query;
    }

    // =====================================================================
    //  SCOPES HỖ TRỢ BÁO GIÁ SỰ KIỆN
    // =====================================================================

    /**
     * Lọc theo trạng thái báo giá (quote_status).
     */
    public function scopeQuoteStatus($query, ?int $status)
    {
        if ($status === null) {
            return $query;
        }
        return $query->where('quote_status', $status);
    }

    /**
     * Chỉ lấy báo giá đã được khách duyệt.
     */
    public function scopeApprovedQuotes($query)
    {
        return $query->where('quote_status', self::QUOTE_STATUS_APPROVED);
    }

    /**
     * Chỉ lấy báo giá đang là nháp.
     */
    public function scopeDraftQuotes($query)
    {
        return $query->where('quote_status', self::QUOTE_STATUS_DRAFT);
    }
}
