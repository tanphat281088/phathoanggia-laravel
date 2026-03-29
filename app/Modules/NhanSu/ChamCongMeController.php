<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ChamCong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ChamCongMeController extends BaseController
{
    /**
     * GET /nhan-su/cham-cong/me?from=YYYY-MM-DD&to=YYYY-MM-DD&page=1&per_page=20
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id ?? auth()->id();

        if (!$userId) {
            return $this->respond(false, 'UNAUTHORIZED', null, 401);
        }

        $v = Validator::make($request->all(), [
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

        $to      = $request->input('to') ?: now()->toDateString();
        $from    = $request->input('from') ?: \Carbon\Carbon::parse($to)->subDays(30)->toDateString();
        $perPage = (int) $request->input('per_page', 20);
        $page    = (int) $request->input('page', 1);
        $type    = $request->input('type');
        $within  = $request->has('within') ? (int) $request->input('within') : null;
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        try {
            $query = ChamCong::query()
                ->ofUser($userId)
                ->between($from . ' 00:00:00', $to . ' 23:59:59')
                ->with(['workpoint:id,ten']);

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
                $workpointName = null;
                $workpointId   = null;

                if ($c->relationLoaded('workpoint') && $c->workpoint) {
                    $workpointId   = $c->workpoint->id ?? null;
                    $workpointName = $c->workpoint->ten ?? null;
                }

                return [
                    'id'            => $c->id,
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
                    'source'        => $c->device_id ? 'device' : ($c->ip ? 'ip' : null),
                    'workpoint_id'  => $workpointId,
                    'workpoint_ten' => $workpointName,
                    'selfie_url'    => $this->makeSelfieDataUrl($c->selfie_path),
                ];
            });

            return $this->respond(true, 'MY_ATTENDANCE', [
                'filter' => [
                    'from'   => $from,
                    'to'     => $to,
                    'type'   => $type,
                    'within' => $within,
                    'order'  => $order,
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

    private function makeSelfieDataUrl(?string $selfiePath): ?string
    {
        if (!$selfiePath) {
            return null;
        }

        $full = storage_path('app/public/' . ltrim($selfiePath, '/'));

        if (!is_file($full) || !is_readable($full)) {
            return null;
        }

        $bin = @file_get_contents($full);
        if ($bin === false || $bin === '') {
            return null;
        }

        $mime = @mime_content_type($full) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode($bin);
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
