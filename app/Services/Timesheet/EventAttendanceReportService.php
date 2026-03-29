<?php

namespace App\Services\Timesheet;

use App\Models\ChamCong;
use App\Models\DiemLamViec;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * EventAttendanceReportService
 *
 * Báo cáo chấm công theo ĐỊA ĐIỂM (workpoint) + NHÂN VIÊN trong một khoảng ngày.
 *
 * Mô hình mới:
 * - Không còn lấy first-in / last-out của cả ngày một cách thô.
 * - Tính theo TỪNG PHIÊN hợp lệ trong ngày (checkin -> checkout).
 * - Nếu cùng ngày có nhiều phiên tại cùng 1 workpoint, sẽ cộng tổng phút đúng.
 */
class EventAttendanceReportService
{
    protected WorkdayRule $rule;

    public function __construct()
    {
        $this->rule = new WorkdayRule();
    }

    /**
     * @param  Carbon      $from
     * @param  Carbon      $to
     * @param  int|null    $workpointId
     * @param  int|null    $userId
     * @return array
     */
    public function report(Carbon $from, Carbon $to, ?int $workpointId = null, ?int $userId = null): array
    {
        if (!$this->rule->enabled()) {
            return [
                'range'     => [$from->toDateString(), $to->toDateString()],
                'items'     => [],
                'workpoint' => $workpointId,
                'user'      => $userId,
                'note'      => 'Timesheet.disabled = true trong config(timesheet.enabled).',
            ];
        }

        $logs = ChamCong::query()
            ->valid()
            ->whereBetween('checked_at', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($workpointId, fn ($q) => $q->where('workpoint_id', $workpointId))
            ->orderBy('user_id')
            ->orderBy('workpoint_id')
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'workpoint_id',
                'type',
                'checked_at',
            ]);

        if ($logs->isEmpty()) {
            return [
                'range'     => [$from->toDateString(), $to->toDateString()],
                'items'     => [],
                'workpoint' => $workpointId,
                'user'      => $userId,
            ];
        }

        // ===== 1) Gom theo (user_id, workpoint_id, yyyy-MM-dd)
        $grouped = $logs->groupBy(function (ChamCong $c) {
            $day = $c->checked_at ? $c->checked_at->toDateString() : null;

            return implode('|', [
                (int) $c->user_id,
                (int) ($c->workpoint_id ?? 0),
                $day,
            ]);
        });

        $perDay = []; // key user|workpoint|date => aggregate

        foreach ($grouped as $key => $items) {
            /** @var Collection $items */
            if ($items->isEmpty()) {
                continue;
            }

            [$uid, $wid, $dayStr] = explode('|', $key);

            if (!$dayStr) {
                continue;
            }

            $sessions = $this->buildSessionsForGroup($items, $dayStr);

            if (empty($sessions)) {
                continue;
            }

            $firstCheckin = $sessions[0]['checkin_at'];
            $lastCheckout = $sessions[count($sessions) - 1]['checkout_at'];
            $totalMinutes = array_reduce(
                $sessions,
                fn (int $carry, array $s) => $carry + (int) ($s['minutes'] ?? 0),
                0
            );

            $perDay[$key] = [
                'user_id'      => (int) $uid,
                'workpoint_id' => (int) $wid,
                'ngay'         => $dayStr,
                'minutes'      => $totalMinutes,
                'checkin_at'   => $firstCheckin,
                'checkout_at'  => $lastCheckout,
            ];
        }

        if (empty($perDay)) {
            return [
                'range'     => [$from->toDateString(), $to->toDateString()],
                'items'     => [],
                'workpoint' => $workpointId,
                'user'      => $userId,
            ];
        }

        // ===== 2) Gom tổng theo (workpoint_id, user_id)
        $byWpUser = [];

        foreach ($perDay as $row) {
            $uid = $row['user_id'];
            $wid = $row['workpoint_id'];
            $day = $row['ngay'];

            $groupKey = $wid . '|' . $uid;

            if (!isset($byWpUser[$groupKey])) {
                $byWpUser[$groupKey] = [
                    'workpoint_id'  => $wid,
                    'user_id'       => $uid,
                    'total_minutes' => 0,
                    'total_days'    => 0,
                    'first_checkin' => null,
                    'last_checkout' => null,
                    'by_days'       => [],
                ];
            }

            $byWpUser[$groupKey]['total_minutes'] += (int) $row['minutes'];
            $byWpUser[$groupKey]['total_days']    += 1;
            $byWpUser[$groupKey]['by_days'][$day] = [
                'minutes'     => (int) $row['minutes'],
                'checkin_at'  => $row['checkin_at'],
                'checkout_at' => $row['checkout_at'],
            ];

            if (
                $byWpUser[$groupKey]['first_checkin'] === null ||
                $row['checkin_at'] < $byWpUser[$groupKey]['first_checkin']
            ) {
                $byWpUser[$groupKey]['first_checkin'] = $row['checkin_at'];
            }

            if (
                $byWpUser[$groupKey]['last_checkout'] === null ||
                $row['checkout_at'] > $byWpUser[$groupKey]['last_checkout']
            ) {
                $byWpUser[$groupKey]['last_checkout'] = $row['checkout_at'];
            }
        }

        // ===== 3) Bind thêm thông tin user & workpoint
        $userIds = array_values(array_unique(array_map(
            fn ($row) => (int) $row['user_id'],
            array_values($byWpUser)
        )));

        $wpIds = array_values(array_unique(array_map(
            fn ($row) => (int) $row['workpoint_id'],
            array_values($byWpUser)
        )));

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $wps = DiemLamViec::query()
            ->whereIn('id', $wpIds)
            ->get(['id', 'ten', 'lat', 'lng', 'ban_kinh_m'])
            ->keyBy('id');

        $items = [];

        foreach ($byWpUser as $row) {
            /** @var User|null $u */
            $u = $users->get($row['user_id']);
            /** @var DiemLamViec|null $w */
            $w = $wps->get($row['workpoint_id']);

            $items[] = [
                'workpoint' => $w ? [
                    'id'         => (int) $w->id,
                    'ten'        => $w->ten,
                    'lat'        => (float) $w->lat,
                    'lng'        => (float) $w->lng,
                    'ban_kinh_m' => (int) $w->ban_kinh_m,
                ] : [
                    'id'         => (int) $row['workpoint_id'],
                    'ten'        => null,
                    'lat'        => null,
                    'lng'        => null,
                    'ban_kinh_m' => null,
                ],
                'user' => $u ? [
                    'id'    => (int) $u->id,
                    'name'  => $u->name,
                    'email' => $u->email,
                ] : [
                    'id'    => (int) $row['user_id'],
                    'name'  => null,
                    'email' => null,
                ],
                'stats' => [
                    'total_minutes' => (int) $row['total_minutes'],
                    'total_days'    => (int) $row['total_days'],
                    'first_checkin' => $row['first_checkin'],
                    'last_checkout' => $row['last_checkout'],
                ],
                'by_days' => $row['by_days'],
            ];
        }

        return [
            'range'     => [$from->toDateString(), $to->toDateString()],
            'workpoint' => $workpointId,
            'user'      => $userId,
            'items'     => $items,
        ];
    }

    /**
     * Build các phiên hợp lệ trong 1 group: (user_id, workpoint_id, day)
     *
     * Rule:
     * - checkin mở phiên
     * - checkout đóng phiên
     * - checkout mồ côi => bỏ qua
     * - nhiều checkin liên tiếp khi chưa checkout => giữ checkin mới nhất
     */
    protected function buildSessionsForGroup(Collection $items, string $dayStr): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $day = Carbon::parse($dayStr, $tz)->startOfDay();
        [$b1, $b2] = $this->rule->breakPeriod($day);

        $sorted = $items->sortBy(function (ChamCong $c) {
            return [
                $c->checked_at?->timestamp ?? 0,
                $c->id,
            ];
        })->values();

        $sessions = [];
        $currentIn = null;

        foreach ($sorted as $log) {
            if (!$log->checked_at) {
                continue;
            }

            $at = $log->checked_at->copy()->setTimezone($tz);

            if ($log->type === 'checkin') {
                $currentIn = $at;
                continue;
            }

            if ($log->type === 'checkout') {
                if (!$currentIn) {
                    continue;
                }

                if ($at->lessThanOrEqualTo($currentIn)) {
                    continue;
                }

                $minutes = $this->minutesExcludingBreak($currentIn, $at, $b1, $b2);

                $sessions[] = [
                    'checkin_at'  => $currentIn->toDateTimeString(),
                    'checkout_at' => $at->toDateTimeString(),
                    'minutes'     => max(0, (int) $minutes),
                ];

                $currentIn = null;
            }
        }

        return $sessions;
    }

    /**
     * Tính tổng phút làm việc giữa [$startAt, $endAt]
     * sau khi TRỪ phần giao với giờ nghỉ [$b1,$b2].
     */
    protected function minutesExcludingBreak(Carbon $startAt, Carbon $endAt, Carbon $b1, Carbon $b2): int
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

        $startAt = $startAt->copy()->setTimezone($tz);
        $endAt   = $endAt->copy()->setTimezone($tz);
        $b1      = $b1->copy()->setTimezone($tz);
        $b2      = $b2->copy()->setTimezone($tz);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            return 0;
        }

        $all = intdiv($endAt->getTimestamp() - $startAt->getTimestamp(), 60);
        if ($all <= 0) {
            return 0;
        }

        if ($endAt->lte($b1) || $startAt->gte($b2)) {
            return $all;
        }

        $overlapStart = $startAt->max($b1);
        $overlapEnd   = $endAt->min($b2);

        $overlap = 0;
        if ($overlapEnd->greaterThan($overlapStart)) {
            $overlap = intdiv($overlapEnd->getTimestamp() - $overlapStart->getTimestamp(), 60);
        }

        $worked = $all - $overlap;
        return $worked > 0 ? $worked : 0;
    }
}