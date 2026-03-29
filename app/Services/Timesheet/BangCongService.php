<?php

namespace App\Services\Timesheet;

use App\Models\BangCongThang;
use App\Models\ChamCong;
use App\Models\DonTuNghiPhep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BangCongService
{
    protected WorkdayRule $rule;
    protected HolidayCalendar $calendar;

    public function __construct()
    {
        $this->rule     = new WorkdayRule();
        $this->calendar = new HolidayCalendar();
    }

    /**
     * Tổng hợp bảng công cho 1 kỳ/tháng (YYYY-MM).
     * Quy ước:
     * - "thang" là THÁNG BẮT ĐẦU kỳ 6→5
     * - nhiều phiên làm việc trong cùng 1 ngày được hỗ trợ
     */
    public function computeMonth(string $thang, ?int $userId = null): void
    {
        [$start, $end] = $this->cycleRange($thang);

        \Log::info('Timesheet::computeMonth START', [
            'thang'   => $thang,
            'range'   => [$start->toDateTimeString(), $end->toDateTimeString()],
            'user_id' => $userId,
        ]);

        $userIds = $this->collectUserIds($thang, $start, $end, $userId);

        foreach ($userIds as $uid) {
            DB::transaction(function () use ($uid, $thang, $start, $end) {
                $existing = BangCongThang::query()
                    ->ofUser($uid)
                    ->month($thang)
                    ->first();

                if ($existing && $existing->locked) {
                    \Log::info('Timesheet::computeMonth SKIP locked row', [
                        'uid'   => $uid,
                        'thang' => $thang,
                    ]);
                    return;
                }

                // Build phiên làm việc hợp lệ theo ngày
                $sessionsByDay = $this->buildDailySessions($uid, $start, $end);

                // 1) Ngày công: đếm ngày có ít nhất 1 phiên đóng hợp lệ
                $workedDays = $this->countWorkedDaysAdvanced($sessionsByDay);

                // 2) Nghỉ phép / không lương
                [$npNgay, $npGio, $klNgay, $klGio] = $this->sumLeaves($uid, $start, $end);

                // 3) Tổng phút công + đi trễ / về sớm / OT
                [$soGioCong, $diTre, $veSom, $otGio] = $this->sumWorkHoursAndLateEarlyOT($uid, $sessionsByDay, $start, $end);

                $sessionDays = count(array_filter($sessionsByDay, fn ($x) => !empty($x)));
                $sessionCount = array_reduce(
                    $sessionsByDay,
                    fn (int $carry, array $x) => $carry + count($x),
                    0
                );

                $note = [
                    'computed_by' => 'BangCongService::computeMonth',
                    'range'       => [$start->toDateString(), $end->toDateString()],
                    'session_days'=> $sessionDays,
                    'session_count' => $sessionCount,
                    'rule'        => [
                        'enabled'       => $this->rule->enabled(),
                        'start'         => $this->rule->start(),
                        'end'           => $this->rule->end(),
                        'break_start'   => $this->rule->breakStart(),
                        'break_end'     => $this->rule->breakEnd(),
                        'grace_minutes' => $this->rule->grace(),
                        'ot' => [
                            'enabled'         => (bool) config('timesheet.ot.enabled', false),
                            'after_end_only'  => (bool) config('timesheet.ot.after_end_only', true),
                            'min_minutes'     => (int)  config('timesheet.ot.min_minutes', 10),
                        ],
                        'calendar' => [
                            'weekend' => [
                                'enabled' => $this->calendar->weekendEnabled(),
                                'days'    => $this->calendar->weekendDays(),
                                'exclude' => $this->calendar->weekendExcludeFromWorkedDays(),
                            ],
                            'holiday' => [
                                'enabled'  => $this->calendar->holidayEnabled(),
                                'list_cnt' => count($this->calendar->holidays()),
                                'exclude'  => $this->calendar->holidayExcludeFromWorkedDays(),
                            ],
                        ],
                    ],
                ];

                // ÉP KIỂU AN TOÀN
                $workedDays = (int) $workedDays;
                $soGioCong  = max(0, (int) round($soGioCong)); // đang lưu PHÚT công
                $diTre      = max(0, (int) round($diTre));
                $veSom      = max(0, (int) round($veSom));
                $npNgay     = max(0, (int) round($npNgay));
                $npGio      = max(0, (int) round($npGio));
                $klNgay     = max(0, (int) round($klNgay));
                $klGio      = max(0, (int) round($klGio));
                $otGio      = max(0, (int) round($otGio)); // đang lưu PHÚT OT

                BangCongThang::query()->updateOrCreate(
                    ['user_id' => $uid, 'thang' => $thang],
                    [
                        'so_ngay_cong'          => $workedDays,
                        'so_gio_cong'           => $soGioCong,
                        'di_tre_phut'           => $diTre,
                        've_som_phut'           => $veSom,
                        'nghi_phep_ngay'        => $npNgay,
                        'nghi_phep_gio'         => $npGio,
                        'nghi_khong_luong_ngay' => $klNgay,
                        'nghi_khong_luong_gio'  => $klGio,
                        'lam_them_gio'          => $otGio,
                        'ghi_chu'               => $note,
                        'computed_at'           => now(),
                    ]
                );

                \Log::info('Timesheet::computeMonth DONE row', [
                    'uid'          => $uid,
                    'thang'        => $thang,
                    'workedDays'   => $workedDays,
                    'soGioCong'    => $soGioCong,
                    'late'         => $diTre,
                    'early'        => $veSom,
                    'ot'           => $otGio,
                    'session_days' => $sessionDays,
                    'session_count'=> $sessionCount,
                ]);
            });
        }
    }

    /**
     * Range tháng dương lịch (giữ để tương thích nếu nơi khác vẫn dùng)
     */
    private function monthRange(string $thang): array
    {
        $start = Carbon::createFromFormat('Y-m', $thang)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start, $end];
    }

    /**
     * Range kỳ công 6→5 theo config timesheet.cycle_start_day
     * VD: 2025-10 -> [2025-10-06 00:00:00, 2025-11-05 23:59:59]
     */
    private function cycleRange(string $thang): array
    {
        $startDay = (int) config('timesheet.cycle_start_day', 6);

        $start = Carbon::createFromFormat('Y-m', $thang)
            ->day($startDay)
            ->startOfDay();

        $end = (clone $start)->addMonthNoOverflow()->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * Suy ra nhãn kỳ (YYYY-MM) cho một ngày bất kỳ theo quy tắc 6→5.
     */
    public static function cycleLabelForDate(Carbon $date): string
    {
        $startDay = (int) config('timesheet.cycle_start_day', 6);
        $d = $date->copy();

        if ((int) $d->day < $startDay) {
            $d->subMonthNoOverflow();
        }

        return $d->format('Y-m');
    }

    /**
     * Lấy danh sách user cần tính:
     * - nếu có $userId => chỉ user đó
     * - nếu không:
     *   + user có log chấm công trong kỳ
     *   + user có đơn từ overlap kỳ
     *   + user đã có BangCongThang của kỳ (để recompute không bỏ sót row cũ)
     */
    private function collectUserIds(string $thang, Carbon $start, Carbon $end, ?int $userId = null): array
    {
        if ($userId) {
            return [(int) $userId];
        }

        $uids = [];

        $uids = array_merge(
            $uids,
            ChamCong::query()
                ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->distinct()
                ->pluck('user_id')
                ->all()
        );

        $uids = array_merge(
            $uids,
            DonTuNghiPhep::query()
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tu_ngay', [$start->toDateString(), $end->toDateString()])
                        ->orWhereBetween('den_ngay', [$start->toDateString(), $end->toDateString()])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('tu_ngay', '<=', $start->toDateString())
                                ->where('den_ngay', '>=', $end->toDateString());
                        })
                        ->orWhereNull('tu_ngay')
                        ->orWhereNull('den_ngay');
                })
                ->distinct()
                ->pluck('user_id')
                ->all()
        );

        $uids = array_merge(
            $uids,
            BangCongThang::query()
                ->where('thang', $thang)
                ->distinct()
                ->pluck('user_id')
                ->all()
        );

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * Build các phiên làm việc hợp lệ trong từng ngày.
     *
     * Rule ghép phiên:
     * - checkin gần nhất mở 1 phiên
     * - checkout tiếp theo sẽ đóng phiên
     * - checkout mồ côi => bỏ qua
     * - nhiều checkin liên tiếp khi chưa checkout => giữ checkin MỚI NHẤT để tránh overcount
     *
     * Kết quả:
     * [
     *   '2026-03-29' => [
     *      ['checkin' => Carbon, 'checkout' => Carbon, 'minutes' => 120],
     *      ...
     *   ],
     *   ...
     * ]
     */
    private function buildDailySessions(int $userId, Carbon $start, Carbon $end): array
    {
        $logs = ChamCong::query()
            ->ofUser($userId)
            ->valid()
            ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get(['id', 'type', 'checked_at', 'workpoint_id']);

        if ($logs->isEmpty()) {
            return [];
        }

        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $byDay = [];

        foreach ($logs as $log) {
            $date = $log->checked_at->copy()->setTimezone($tz)->toDateString();
            $byDay[$date] ??= [];
            $byDay[$date][] = $log;
        }

        $sessionsByDay = [];

        foreach ($byDay as $date => $items) {
            $day = Carbon::parse($date, $tz)->startOfDay();
            [$b1, $b2] = $this->rule->breakPeriod($day);

            $currentIn = null;

            foreach ($items as $log) {
                if ($log->type === 'checkin') {
                    // Nếu bị checkin liên tiếp, dùng checkin mới nhất để tránh overcount
                    $currentIn = $log->checked_at->copy()->setTimezone($tz);
                    continue;
                }

                if ($log->type === 'checkout') {
                    if (!$currentIn) {
                        continue;
                    }

                    $checkoutAt = $log->checked_at->copy()->setTimezone($tz);

                    if ($checkoutAt->lessThanOrEqualTo($currentIn)) {
                        continue;
                    }

                    $minutes = $this->minutesExcludingBreak($currentIn, $checkoutAt, $b1, $b2);

                    $sessionsByDay[$date] ??= [];
                    $sessionsByDay[$date][] = [
                        'checkin'  => $currentIn->copy(),
                        'checkout' => $checkoutAt->copy(),
                        'minutes'  => max(0, (int) $minutes),
                    ];

                    $currentIn = null;
                }
            }
        }

        return $sessionsByDay;
    }

    /**
     * Đếm ngày công:
     * - ngày có ít nhất 1 phiên hợp lệ
     * - có thể loại trừ weekend/holiday theo config
     */
    private function countWorkedDaysAdvanced(array $sessionsByDay): int
    {
        $count = 0;

        foreach ($sessionsByDay as $date => $sessions) {
            if (empty($sessions)) {
                continue;
            }

            $day = Carbon::parse($date);

            if ($this->calendar->isWeekend($day) && $this->calendar->weekendExcludeFromWorkedDays()) {
                continue;
            }

            if ($this->calendar->isHoliday($day) && $this->calendar->holidayExcludeFromWorkedDays()) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Cộng tổng PHÚT công + đi trễ / về sớm + PHÚT OT.
     *
     * Trả về:
     * [soGioCong, diTrePhut, veSomPhut, otGio]
     *
     * Lưu ý:
     * - soGioCong hiện đang lưu PHÚT công
     * - otGio hiện đang lưu PHÚT OT
     */
    private function sumWorkHoursAndLateEarlyOT(int $userId, array $sessionsByDay, Carbon $start, Carbon $end): array
    {
        if (!$this->rule->enabled()) {
            return [0, 0, 0, 0];
        }

        if (empty($sessionsByDay)) {
            return [0, 0, 0, 0];
        }

        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

        $totalMinutes = 0;
        $late = 0;
        $early = 0;
        $otMinutes = 0;

        $otEnabled      = (bool) config('timesheet.ot.enabled', false);
        $otAfterEndOnly = (bool) config('timesheet.ot.after_end_only', true);
        $otMin          = (int)  config('timesheet.ot.min_minutes', 10);

        foreach ($sessionsByDay as $date => $sessions) {
            if (empty($sessions)) {
                continue;
            }

            $day = Carbon::parse($date, $tz)->startOfDay();

            $dayWorked = array_reduce(
                $sessions,
                fn (int $carry, array $s) => $carry + (int) ($s['minutes'] ?? 0),
                0
            );

            $totalMinutes += $dayWorked;

            /** @var Carbon $firstIn */
            $firstIn = $sessions[0]['checkin']->copy()->setTimezone($tz);
            /** @var Carbon $lastOut */
            $lastOut = $sessions[count($sessions) - 1]['checkout']->copy()->setTimezone($tz);

            $expectedIn  = $this->rule->dayStart($day)->copy()->setTimezone($tz)->addMinutes($this->rule->grace());
            $expectedOut = $this->rule->dayEnd($day)->copy()->setTimezone($tz)->subMinutes($this->rule->grace());

            $lateDelta  = intdiv(max(0, $firstIn->getTimestamp() - $expectedIn->getTimestamp()), 60);
            $earlyDelta = intdiv(max(0, $expectedOut->getTimestamp() - $lastOut->getTimestamp()), 60);

            $late += $lateDelta;
            $early += $earlyDelta;

            if ($otEnabled) {
                $dailyOT = 0;

                foreach ($sessions as $session) {
                    /** @var Carbon $in */
                    $in = $session['checkin']->copy()->setTimezone($tz);
                    /** @var Carbon $out */
                    $out = $session['checkout']->copy()->setTimezone($tz);

                    if ($otAfterEndOnly) {
                        $dailyOT += $this->minutesAfter($in, $out, $expectedOut);
                    } else {
                        $dailyOT += $this->minutesBefore($in, $out, $expectedIn);
                        $dailyOT += $this->minutesAfter($in, $out, $expectedOut);
                    }
                }

                if ($dailyOT >= $otMin) {
                    $otMinutes += $dailyOT;
                }
            }
        }

        $soGioCong = $totalMinutes; // PHÚT công
        $otGio     = $otMinutes;    // PHÚT OT

        \Log::debug('TS:hours', [
            'user_id'     => $userId,
            'range'       => [$start->toDateTimeString(), $end->toDateTimeString()],
            'enabled'     => $this->rule->enabled(),
            'tot_min'     => $totalMinutes,
            'so_gio_cong' => $soGioCong,
            'late_min'    => $late,
            'early_min'   => $early,
            'ot_min'      => $otMinutes,
        ]);

        return [$soGioCong, $late, $early, $otGio];
    }

    /**
     * Tổng phút giữa [$startAt,$endAt] sau khi TRỪ phần giao với giờ nghỉ [$b1,$b2].
     */
    private function minutesExcludingBreak(Carbon $startAt, Carbon $endAt, Carbon $b1, Carbon $b2): int
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

    /**
     * Số phút nằm TRƯỚC mốc $cutoff.
     */
    private function minutesBefore(Carbon $startAt, Carbon $endAt, Carbon $cutoff): int
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

        $startAt = $startAt->copy()->setTimezone($tz);
        $endAt   = $endAt->copy()->setTimezone($tz);
        $cutoff  = $cutoff->copy()->setTimezone($tz);

        if ($startAt->greaterThanOrEqualTo($cutoff)) {
            return 0;
        }

        $effectiveEnd = $endAt->lte($cutoff) ? $endAt : $cutoff;

        if ($effectiveEnd->lessThanOrEqualTo($startAt)) {
            return 0;
        }

        return intdiv($effectiveEnd->getTimestamp() - $startAt->getTimestamp(), 60);
    }

    /**
     * Số phút nằm SAU mốc $cutoff.
     */
    private function minutesAfter(Carbon $startAt, Carbon $endAt, Carbon $cutoff): int
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

        $startAt = $startAt->copy()->setTimezone($tz);
        $endAt   = $endAt->copy()->setTimezone($tz);
        $cutoff  = $cutoff->copy()->setTimezone($tz);

        if ($endAt->lessThanOrEqualTo($cutoff)) {
            return 0;
        }

        $effectiveStart = $startAt->gte($cutoff) ? $startAt : $cutoff;

        if ($endAt->lessThanOrEqualTo($effectiveStart)) {
            return 0;
        }

        return intdiv($endAt->getTimestamp() - $effectiveStart->getTimestamp(), 60);
    }

    /**
     * Tổng hợp nghỉ phép theo overlap tu_ngay/den_ngay trong khoảng kỳ [start,end].
     */
    private function sumLeaves(int $userId, Carbon $start, Carbon $end): array
    {
        $items = DonTuNghiPhep::query()
            ->ofUser($userId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tu_ngay', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('den_ngay', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('tu_ngay', '<=', $start->toDateString())
                            ->where('den_ngay', '>=', $end->toDateString());
                    })
                    ->orWhereNull('tu_ngay')
                    ->orWhereNull('den_ngay');
            })
            ->get();

        $npNgay = 0;
        $npGio = 0;
        $klNgay = 0;
        $klGio = 0;

        foreach ($items as $r) {
            if (!$r->isApproved()) {
                continue;
            }

            $loai  = $r->loai;
            $soGio = (int) ($r->so_gio ?? 0);

            $from = $r->tu_ngay ? Carbon::parse($r->tu_ngay)->startOfDay() : null;
            $to   = $r->den_ngay ? Carbon::parse($r->den_ngay)->endOfDay() : null;

            $overlapDays = 0;
            if ($from && $to) {
                $overlapStart = $from->max($start);
                $overlapEnd   = $to->min($end);

                if ($overlapStart <= $overlapEnd) {
                    $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                }
            }

            if ($loai === DonTuNghiPhep::LOAI_NGHI_PHEP) {
                $npNgay += $overlapDays;
                $npGio  += $soGio;
            } elseif ($loai === DonTuNghiPhep::LOAI_KHONG_LUONG) {
                $klNgay += $overlapDays;
                $klGio  += $soGio;
            }
        }

        return [$npNgay, $npGio, $klNgay, $klGio];
    }
}