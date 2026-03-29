<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ChamCong
 *
 * - Lưu checkin/checkout theo GPS + khuôn mặt.
 * - Ràng buộc duy nhất theo (user_id, type, ngay) đã đặt ở migration.
 */
class ChamCong extends Model
{
    use HasFactory;

    protected $table = 'cham_congs';

    /**
     * Cho phép fill các thuộc tính này (mass assignment).
     * Giữ đầy đủ field cũ, thêm field mới cho workpoint & face.
     */
    protected $fillable = [
        'user_id',
        'workpoint_id',        // ✅ NEW: link tới DiemLamViec (địa điểm làm việc)
        'type',                // 'checkin' | 'checkout'
        'lat',
        'lng',
        'accuracy_m',
        'distance_m',
        'within_geofence',     // 1|0
        'device_id',
        'ip',
        'checked_at',
        'ghi_chu',

        // ✅ NEW: thông tin khuôn mặt
        'selfie_path',
        'face_match',
        'face_score',
        'face_provider',
        'face_checked_at',
        'reason',
        'cancelled',
        'cancelled_at',
    ];

    /**
     * Kiểu dữ liệu cast tự động.
     */
    protected $casts = [
        'lat'              => 'float',
        'lng'              => 'float',
        'accuracy_m'       => 'integer',
        'distance_m'       => 'integer',
        'within_geofence'  => 'boolean',
        'checked_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',

        // ✅ NEW
        'face_match'       => 'boolean',
        'face_score'       => 'integer',
        'face_checked_at'  => 'datetime',
        'cancelled'        => 'boolean',
        'cancelled_at'     => 'datetime',
    ];

    // ===== Quan hệ =====

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅ NEW: Địa điểm làm việc (geofence: văn phòng / nhà hàng / event site)
     */
    public function workpoint()
    {
        // Model DiemLamViec đã được dùng trong controller chấm công
        return $this->belongsTo(DiemLamViec::class, 'workpoint_id');
    }

    // ===== Scopes tiện ích =====

    /**
     * Lọc theo user.
     */
    public function scopeOfUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /**
     * Lọc theo khoảng thời gian checked_at (bao trùm).
     */
    public function scopeBetween(Builder $q, $from = null, $to = null): Builder
    {
        if ($from) {
            $q->where('checked_at', '>=', $from);
        }
        if ($to) {
            $q->where('checked_at', '<=', $to);
        }
        return $q;
    }

    /**
     * Lọc theo ngày (YYYY-MM-DD).
     */
    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->whereRaw('DATE(checked_at) = ?', [$date]);
    }

    /**
     * Chỉ check-in.
     */
    public function scopeCheckin(Builder $q): Builder
    {
        return $q->where('type', 'checkin');
    }

    /**
     * Chỉ check-out.
     */
    public function scopeCheckout(Builder $q): Builder
    {
        return $q->where('type', 'checkout');
    }

    /**
     * ✅ NEW: chỉ lấy các log "hợp lệ" (không bị cancelled).
     * (Chưa dùng ngay, nhưng sẽ rất tiện cho Timesheet sau này nếu anh muốn bỏ qua log lỗi)
     */
    public function scopeValid(Builder $q): Builder
    {
        return $q->where(function (Builder $qq) {
            $qq->whereNull('cancelled')
               ->orWhere('cancelled', false);
        });
    }

    // ===== Helpers =====

    public function isCheckin(): bool
    {
        return $this->type === 'checkin';
    }

    public function isCheckout(): bool
    {
        return $this->type === 'checkout';
    }

    /**
     * Nhãn tiếng Việt cho type.
     */
    public function typeLabel(): string
    {
        return $this->isCheckin() ? 'Chấm công vào' : 'Chấm công ra';
    }

    /**
     * Nhãn tiếng Việt cho within_geofence.
     */
    public function withinLabel(): string
    {
        return $this->within_geofence ? 'trong khu vực' : 'ngoài khu vực';
    }

    /**
     * ✅ NEW: log đã bị hủy / không được tính công (do sai khuôn mặt / sai vị trí / thiếu dữ liệu).
     */
    public function isCancelled(): bool
    {
        return (bool) $this->cancelled;
    }

    /**
     * ✅ NEW: đã chạy đối chiếu khuôn mặt?
     */
    public function isFaceChecked(): bool
    {
        return $this->face_checked_at instanceof CarbonInterface || !is_null($this->face_checked_at);
    }

    /**
     * ✅ NEW: khuôn mặt khớp & vượt ngưỡng?
     * Lưu ý: ngưỡng chuẩn lấy từ config face.threshold để đảm bảo đồng bộ BE.
     */
    public function isFaceOk(): bool
    {
        if ($this->face_match === null) {
            return false;
        }

        $threshold = (int) config('face.aws_gateway.threshold', 90);

        return $this->face_match && (int) $this->face_score >= $threshold;
    }

    /**
     * Trả về chuỗi mô tả ngắn, phục vụ log/audit (tiếng Việt).
     */
    public function shortDesc(): string
    {
        $when = $this->checked_at instanceof CarbonInterface
            ? $this->checked_at->format('Y-m-d H:i')
            : (string) $this->checked_at;

        // Giữ distance theo mét như cũ (d=7m)
        $distance = is_null($this->distance_m) ? '' : ('d=' . (string) $this->distance_m . 'm');

        // Thêm label khuôn mặt rất ngắn gọn: [face OK]/[face FAIL]/[face ?]
        if ($this->isFaceChecked()) {
            if ($this->isFaceOk()) {
                $face = 'face OK';
            } elseif ($this->face_match === false) {
                $face = 'face FAIL';
            } else {
                $face = 'face ?';
            }
            $facePart = ' — ' . $face . ' (' . (int) $this->face_score . '%)';
        } else {
            $facePart = '';
        }

        // Ví dụ: "Chấm công vào lúc 2025-10-23 09:30 — d=7m — trong khu vực — face OK (95%)"
        return sprintf(
            '%s lúc %s — %s — %s%s',
            $this->typeLabel(),
            $when,
            $distance,
            $this->withinLabel(),
            $facePart
        );
    }
}
