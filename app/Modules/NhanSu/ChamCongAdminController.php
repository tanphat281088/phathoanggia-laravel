<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ChamCong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ChamCongAdminController extends BaseController
{
    /**
     * GET /nhan-su/cham-cong?user_id=&from=YYYY-MM-DD&to=YYYY-MM-DD&page=1&per_page=20&type=&within=&order=
     *
     * - Xem log chấm công của nhân viên
     * - Mặc định: 30 ngày gần nhất
     * - Có filter theo user_id / type / within / order
     * - Load cả user + workpoint để FE hiển thị đúng địa điểm
     */
    public function index(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id'  => ['nullable', 'integer', 'min:1'],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],

            'type'     => ['nullable', 'in:checkin,checkout'],
            'within'   => ['nullable', 'in:0,1'],
            'order'    => ['nullable', 'in:asc,desc'],
        ]);

        if ($v->fails()) {
            return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
        }

        $to   = $request->input('to') ?: now()->toDateString();
        $from = $request->input('from') ?: \Carbon\Carbon::parse($to)->subDays(30)->toDateString();

        $perPage = (int) ($request->input('per_page', 20));
        $page    = (int) ($request->input('page', 1));
        $userId  = $request->input('user_id');

        $type   = $request->input('type');
        $within = $request->has('within') ? (int) $request->input('within') : null;
        $order  = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        try {
            $query = ChamCong::query()
                ->valid()
                ->between($from . ' 00:00:00', $to . ' 23:59:59')
                ->with([
                    'user:id,name,email',
                    'workpoint:id,ten',
                ]);

            if ($userId) {
                $query->ofUser((int) $userId);
            }

            if ($type) {
                $query->{$type}();
            }

            if ($within !== null) {
                $query->where('within_geofence', (bool) $within);
            }

            if ($order === 'asc') {
                $query->orderBy('checked_at', 'asc')->orderBy('id', 'asc');
            } else {
                $query->orderBy('checked_at', 'desc')->orderBy('id', 'desc');
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(function (ChamCong $c) {
                $displayName = null;
                if ($c->relationLoaded('user') && $c->user) {
                    $displayName = $c->user->name ?? $c->user->email ?? ('#' . $c->user->id);
                }

                $workpointName = null;
                $workpointId   = null;
                if ($c->relationLoaded('workpoint') && $c->workpoint) {
                    $workpointId   = $c->workpoint->id ?? null;
                    $workpointName = $c->workpoint->ten ?? null;
                }

                return [
                    'id'            => $c->id,
                    'user_id'       => $c->user_id,
                    'user_name'     => $displayName,
                    'type'          => $c->type,
                    'checked_at'    => optional($c->checked_at)->toIso8601String(),

                    'lat'           => $c->lat,
                    'lng'           => $c->lng,
                    'distance_m'    => $c->distance_m,
                    'within'        => (bool) $c->within_geofence,
                    'accuracy_m'    => $c->accuracy_m,
                    'device_id'     => $c->device_id,
                    'ip'            => $c->ip,
                    'ghi_chu'       => $c->ghi_chu,
                    'short_desc'    => $c->shortDesc(),

                    'ngay'          => $c->checked_at ? $c->checked_at->toDateString() : null,
                    'gio_phut'      => $c->checked_at ? $c->checked_at->format('H:i') : null,
                    'weekday'       => $c->checked_at ? $c->checked_at->locale('vi')->isoFormat('ddd') : null,

                    'workpoint_id'  => $workpointId,
                    'workpoint_ten' => $workpointName,
                ];
            });

            return $this->respond(true, 'ADMIN_ATTENDANCE', [
                'filter' => [
                    'user_id' => $userId ? (int) $userId : null,
                    'from'    => $from,
                    'to'      => $to,
                    'type'    => $type,
                    'within'  => $within,
                    'order'   => $order,
                ],
                'pagination' => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'has_more'     => $paginator->hasMorePages(),
                ],
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            return $this->respond(
                false,
                'SERVER_ERROR',
                config('app.debug') ? ['message' => $e->getMessage()] : ['message' => 'Lỗi hệ thống.'],
                500
            );
        }
    }

    private function respond(bool $success, string $code, $data = null, int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            if ($success) {
                return \App\Class\CustomResponse::success($data, $code)->setStatusCode($status);
            }

            return \App\Class\CustomResponse::failed($data, $code)->setStatusCode($status);
        }

        return response()->json([
            'success' => $success,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }
}