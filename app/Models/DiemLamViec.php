<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\DiemLamViec
 *
 * - Lưu các điểm làm việc (tâm geofence).
 * - Hỗ trợ fixed/event, hiệu lực thời gian, nguồn tạo.
 * - Hỗ trợ tính khoảng cách (Haversine) & kiểm tra within_geofence.
 * - Giữ tương thích ngược với code cũ đang gọi nearest() / active().
 */
class DiemLamViec extends Model
{
    use HasFactory;

    protected $table = 'diem_lam_viecs';

    public const TYPE_FIXED = 'fixed';
    public const TYPE_EVENT = 'event';

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_MOBILE = 'mobile';

    /** Mặc định: bán kính địa cầu (m) cho Haversine */
    public const EARTH_RADIUS_M = 6371000;

    protected $fillable = [
        'ma_dia_diem',
        'ten',
        'loai_dia_diem',
        'nguon_tao',
        'created_by',
        'hieu_luc_tu',
        'hieu_luc_den',
        'dia_chi',
        'ghi_chu',
        'lat',
        'lng',
        'ban_kinh_m',
        'trang_thai',
    ];

    protected $casts = [
        'lat'          => 'float',
        'lng'          => 'float',
        'ban_kinh_m'   => 'integer',
        'trang_thai'   => 'integer',
        'created_by'   => 'integer',
        'hieu_luc_tu'  => 'datetime',
        'hieu_luc_den' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // ===== Quan hệ =====

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chamCongs()
    {
        return $this->hasMany(ChamCong::class, 'workpoint_id');
    }

    // ===== Scopes =====

    /** Chỉ điểm đang bật */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('trang_thai', 1);
    }

    /** Chỉ điểm cố định */
    public function scopeFixed(Builder $q): Builder
    {
        return $q->where('loai_dia_diem', self::TYPE_FIXED);
    }

    /** Chỉ điểm sự kiện */
    public function scopeEvent(Builder $q): Builder
    {
        return $q->where('loai_dia_diem', self::TYPE_EVENT);
    }

    /**
     * Chỉ các điểm còn hiệu lực tại thời điểm $at
     * - fixed thường có hieu_luc_tu/den = null => luôn hợp lệ nếu active
     * - event có thể có start/end => chỉ dùng trong khoảng hiệu lực
     */
    public function scopeAvailableAt(Builder $q, $at = null): Builder
    {
        $at = $at instanceof Carbon ? $at : Carbon::parse($at ?: now());

        return $q->active()
            ->where(function (Builder $qq) use ($at) {
                $qq->whereNull('hieu_luc_tu')
                    ->orWhere('hieu_luc_tu', '<=', $at);
            })
            ->where(function (Builder $qq) use ($at) {
                $qq->whereNull('hieu_luc_den')
                    ->orWhere('hieu_luc_den', '>=', $at);
            });
    }

    // ===== Helpers nghiệp vụ =====

    public function isFixed(): bool
    {
        return $this->loai_dia_diem === self::TYPE_FIXED;
    }

    public function isEvent(): bool
    {
        return $this->loai_dia_diem === self::TYPE_EVENT;
    }

    public function isActive(): bool
    {
        return (int) $this->trang_thai === 1;
    }

    public function isAvailableAt($at = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $at = $at instanceof Carbon ? $at : Carbon::parse($at ?: now());

        if ($this->hieu_luc_tu && $this->hieu_luc_tu->gt($at)) {
            return false;
        }

        if ($this->hieu_luc_den && $this->hieu_luc_den->lt($at)) {
            return false;
        }

        return true;
    }

    public function hasExpiry(): bool
    {
        return !is_null($this->hieu_luc_den);
    }

    public function isExpired($at = null): bool
    {
        if (!$this->hieu_luc_den) {
            return false;
        }

        $at = $at instanceof Carbon ? $at : Carbon::parse($at ?: now());

        return $this->hieu_luc_den->lt($at);
    }

    /**
     * Tính khoảng cách Haversine (m) từ (lat,lng) bất kỳ đến tâm geofence của điểm này.
     */
    public function distanceTo(float $lat, float $lng): int
    {
        $lat1 = deg2rad((float) $this->lat);
        $lng1 = deg2rad((float) $this->lng);
        $lat2 = deg2rad($lat);
        $lng2 = deg2rad($lng);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;

        $c = 2 * asin(min(1, sqrt($a)));

        return (int) round(self::EARTH_RADIUS_M * $c);
    }

    /**
     * Kiểm tra toạ độ (lat,lng) có nằm trong geofence hay không.
     * Trả về: [bool $within, int $distanceM]
     */
    public function withinGeofence(float $lat, float $lng): array
    {
        $distance = $this->distanceTo($lat, $lng);
        return [$distance <= (int) $this->ban_kinh_m, $distance];
    }

    /**
     * Lấy điểm đang còn hiệu lực gần nhất.
     * Giữ tên method nearest() để tương thích code cũ.
     */
    public static function nearest(float $lat, float $lng, $at = null): ?self
    {
        return self::query()
            ->availableAt($at)
            ->get()
            ->sortBy(fn (self $d) => $d->distanceTo($lat, $lng))
            ->first();
    }

    /**
     * Ưu tiên lấy điểm cố định gần nhất còn hiệu lực.
     */
    public static function nearestFixed(float $lat, float $lng, $at = null): ?self
    {
        return self::query()
            ->fixed()
            ->availableAt($at)
            ->get()
            ->sortBy(fn (self $d) => $d->distanceTo($lat, $lng))
            ->first();
    }

    /**
     * Tìm điểm gần đó để tái sử dụng thay vì tạo trùng.
     *
     * Rule:
     * - chỉ xét điểm còn hiệu lực
     * - ưu tiên fixed hơn event
     * - chỉ trả về nếu trong tolerance
     */
    public static function findNearbyReusable(
        float $lat,
        float $lng,
        int $toleranceM = 80,
        $at = null
    ): ?self {
        $points = self::query()
            ->availableAt($at)
            ->get()
            ->map(function (self $point) use ($lat, $lng) {
                $point->near_distance_m = $point->distanceTo($lat, $lng);
                return $point;
            })
            ->sortBy([
                fn (self $p) => $p->isFixed() ? 0 : 1,
                fn (self $p) => (int) ($p->near_distance_m ?? 999999),
            ]);

        /** @var self|null $first */
        $first = $points->first();

        if (!$first) {
            return null;
        }

        return ((int) ($first->near_distance_m ?? 999999) <= $toleranceM) ? $first : null;
    }

    /**
     * Label phục vụ UI / log
     */
    public function typeLabel(): string
    {
        return $this->isFixed() ? 'Cố định' : 'Sự kiện';
    }
}