<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\DiemLamViec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WorkpointController extends BaseController
{
    /**
     * GET /nhan-su/workpoints
     *
     * Hỗ trợ:
     * - lat,lng          : để sort theo khoảng cách gần nhất
     * - q                : tìm theo mã/tên/địa chỉ
     * - type             : fixed | event
     * - only_available   : 1|0 (mặc định 1)
     * - limit            : tối đa 300
     *
     * Trả về:
     * - fixed đứng trước event
     * - nếu có lat/lng thì sort tiếp theo distance_m
     * - event hết hiệu lực sẽ bị ẩn khi only_available=1
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $uid  = $user?->id ?? auth()->id();

        if (!$uid) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        $v = Validator::make($request->all(), [
            'lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'lng'            => ['nullable', 'numeric', 'between:-180,180'],
            'q'              => ['nullable', 'string', 'max:255'],
            'type'           => ['nullable', 'in:fixed,event'],
            'only_available' => ['nullable', 'boolean'],
            'limit'          => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;
        $q   = trim((string) $request->input('q', ''));
        $type = $request->input('type');
        $onlyAvailable = $request->boolean('only_available', true);
        $limit = (int) $request->input('limit', 200);

        $query = DiemLamViec::query();

        if ($onlyAvailable) {
            $query->availableAt(now());
        } else {
            $query->active();
        }

        if ($type) {
            if ($type === DiemLamViec::TYPE_FIXED) {
                $query->fixed();
            } elseif ($type === DiemLamViec::TYPE_EVENT) {
                $query->event();
            }
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('ten', 'like', '%' . $q . '%')
                    ->orWhere('dia_chi', 'like', '%' . $q . '%')
                    ->orWhere('ma_dia_diem', 'like', '%' . $q . '%');
            });
        }

        $rows = $query
            ->orderByRaw("CASE WHEN loai_dia_diem = 'fixed' THEN 0 ELSE 1 END ASC")
            ->orderBy('ten')
            ->limit($limit)
            ->get();

        if (!is_null($lat) && !is_null($lng)) {
            $rows = $rows
                ->map(function (DiemLamViec $wp) use ($lat, $lng) {
                    $wp->distance_m = $wp->distanceTo($lat, $lng);
                    return $wp;
                })
                ->sortBy([
                    fn (DiemLamViec $wp) => $wp->isFixed() ? 0 : 1,
                    fn (DiemLamViec $wp) => (int) ($wp->distance_m ?? 999999),
                    fn (DiemLamViec $wp) => $wp->ten,
                ])
                ->values();
        }

        $data = $rows->map(function (DiemLamViec $wp) {
            return $this->toItem($wp);
        })->all();

        return $this->success([
            'filter' => [
                'lat'            => $lat,
                'lng'            => $lng,
                'q'              => $q !== '' ? $q : null,
                'type'           => $type,
                'only_available' => $onlyAvailable,
                'limit'          => $limit,
            ],
            'items' => $data,
        ], 'WORKPOINT_LIST');
    }

    /**
     * POST /nhan-su/workpoints
     *
     * Mô hình mới:
     * - Nhân viên được tự tạo điểm event
     * - Nhưng có chống trùng:
     *   + nếu gần 1 fixed point => tái sử dụng fixed point
     *   + nếu gần 1 event point còn hiệu lực => tái sử dụng event point
     * - Event point được auto:
     *   + loai_dia_diem = event
     *   + nguon_tao = mobile
     *   + created_by = user hiện tại
     *   + hieu_luc_tu = now()
     *   + hieu_luc_den = hết ngày hôm sau
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $uid  = $user?->id ?? auth()->id();

        if (!$uid) {
            return $this->failed([], 'UNAUTHORIZED', 401);
        }

        $v = Validator::make($request->all(), [
            'ten'        => ['required', 'string', 'max:255'],
            'lat'        => ['required', 'numeric', 'between:-90,90'],
            'lng'        => ['required', 'numeric', 'between:-180,180'],
            'ban_kinh_m' => ['nullable', 'integer', 'min:30', 'max:5000'],
            'dia_chi'    => ['nullable', 'string', 'max:255'],
            'ghi_chu'    => ['nullable', 'string', 'max:2000'],
        ], [], [
            'ten' => 'Tên địa điểm',
            'lat' => 'Vĩ độ (lat)',
            'lng' => 'Kinh độ (lng)',
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $ten    = trim((string) $request->input('ten'));
        $lat    = (float) $request->input('lat');
        $lng    = (float) $request->input('lng');
        $diaChi = trim((string) $request->input('dia_chi', '')) ?: null;
        $ghiChu = trim((string) $request->input('ghi_chu', '')) ?: null;
        $radius = (int) ($request->input('ban_kinh_m') ?: 150);
        $radius = max(30, min($radius, 5000));

        try {
            // ===== 1) Chống trùng/tái sử dụng địa điểm gần đó =====
            $reusable = DiemLamViec::findNearbyReusable($lat, $lng, 80, now());

            if ($reusable) {
                $distance = $reusable->distanceTo($lat, $lng);

                return $this->success([
                    'item'   => $this->toItem($reusable, $distance),
                    'notice' => $reusable->isFixed()
                        ? 'Đã tìm thấy địa điểm cố định gần vị trí hiện tại. Hệ thống sẽ dùng lại địa điểm này, không tạo trùng.'
                        : 'Đã tìm thấy địa điểm sự kiện còn hiệu lực gần vị trí hiện tại. Hệ thống sẽ dùng lại địa điểm này, không tạo trùng.',
                    'reused' => true,
                ], $reusable->isFixed() ? 'WORKPOINT_REUSED_FIXED' : 'WORKPOINT_REUSED_EVENT', 200);
            }

            // ===== 2) Tạo điểm event mới =====
            $now = now();
            $wp = new DiemLamViec();
            $wp->ma_dia_diem   = $this->makeEventCode($uid);
            $wp->ten           = $ten;
            $wp->loai_dia_diem = DiemLamViec::TYPE_EVENT;
            $wp->nguon_tao     = DiemLamViec::SOURCE_MOBILE;
            $wp->created_by    = $uid;
            $wp->hieu_luc_tu   = $now;
            $wp->hieu_luc_den  = $now->copy()->addDay()->endOfDay(); // hết ngày hôm sau
            $wp->dia_chi       = $diaChi;
            $wp->ghi_chu       = $ghiChu;
            $wp->lat           = $lat;
            $wp->lng           = $lng;
            $wp->ban_kinh_m    = $radius;
            $wp->trang_thai    = 1;
            $wp->save();

            return $this->success([
                'item'   => $this->toItem($wp, 0),
                'notice' => 'Đã tạo địa điểm sự kiện mới. Nhân viên tại khu vực này có thể chấm công ngay.',
                'reused' => false,
            ], 'WORKPOINT_CREATED', 201);
        } catch (Throwable $e) {
            return $this->failed(
                config('app.debug') ? ['message' => $e->getMessage()] : ['message' => 'Lỗi hệ thống khi tạo địa điểm.'],
                'SERVER_ERROR',
                500
            );
        }
    }

    // ===== Helpers =====

    private function toItem(DiemLamViec $wp, ?int $distanceM = null): array
    {
        return [
            'id'               => (int) $wp->id,
            'ma_dia_diem'      => $wp->ma_dia_diem,
            'ten'              => $wp->ten,
            'dia_chi'          => $wp->dia_chi,
            'ghi_chu'          => $wp->ghi_chu,
            'lat'              => (float) $wp->lat,
            'lng'              => (float) $wp->lng,
            'ban_kinh_m'       => (int) $wp->ban_kinh_m,
            'trang_thai'       => (int) $wp->trang_thai,
            'loai_dia_diem'    => $wp->loai_dia_diem,
            'loai_label'       => $wp->typeLabel(),
            'nguon_tao'        => $wp->nguon_tao,
            'created_by'       => $wp->created_by ? (int) $wp->created_by : null,
            'hieu_luc_tu'      => $wp->hieu_luc_tu?->toDateTimeString(),
            'hieu_luc_den'     => $wp->hieu_luc_den?->toDateTimeString(),
            'available_now'    => $wp->isAvailableAt(now()),
            'expired'          => $wp->isExpired(now()),
            'distance_m'       => $distanceM,
        ];
    }

    private function makeEventCode(int $uid): string
    {
        // 20 ký tự: EV + ymdHis(12) + uid4 + rand2
        // ví dụ: EV260329154501001205
        do {
            $code = 'EV'
                . now()->format('ymdHis')
                . str_pad((string) ($uid % 10000), 4, '0', STR_PAD_LEFT)
                . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        } while (DiemLamViec::query()->where('ma_dia_diem', $code)->exists());

        return $code;
    }

    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }

        return response()->json([
            'success' => true,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }

    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }

        return response()->json([
            'success' => false,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }
}