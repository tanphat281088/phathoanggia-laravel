<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ChamCong;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ChamCongAdminController extends BaseController
{
    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);
        if ($filters instanceof \Illuminate\Http\JsonResponse) {
            return $filters;
        }

        try {
            $query = $this->buildQuery($filters);

            $paginator = $query->paginate(
                $filters['per_page'],
                ['*'],
                'page',
                $filters['page']
            );

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
                    'user_id' => $filters['user_id'],
                    'from'    => $filters['from'],
                    'to'      => $filters['to'],
                    'type'    => $filters['type'],
                    'within'  => $filters['within'],
                    'order'   => $filters['order'],
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

    public function export(Request $request)
    {
        $filters = $this->resolveFilters($request);
        if ($filters instanceof \Illuminate\Http\JsonResponse) {
            return $filters;
        }

        try {
            $rows = $this->buildQuery($filters)->get()->map(function (ChamCong $c) {
                $displayName = null;
                if ($c->relationLoaded('user') && $c->user) {
                    $displayName = $c->user->name ?? $c->user->email ?? ('#' . $c->user->id);
                }

                $workpointName = null;
                if ($c->relationLoaded('workpoint') && $c->workpoint) {
                    $workpointName = $c->workpoint->ten ?? null;
                }

                return [
                    'Nhân viên'        => $displayName,
                    'Loại'             => $c->type === 'checkin' ? 'Chấm công vào' : 'Chấm công ra',
                    'Ngày'             => $c->checked_at ? $c->checked_at->toDateString() : null,
                    'Giờ'              => $c->checked_at ? $c->checked_at->format('H:i') : null,
                    'Địa điểm'         => $workpointName,
                    'Trong vùng'       => $c->within_geofence ? 'Hợp lệ' : 'Ngoài vùng',
                    'Khoảng cách (m)'  => $c->distance_m,
                    'Thiết bị'         => $c->device_id,
                    'IP'               => $c->ip,
                    'Mô tả'            => $c->shortDesc(),
                ];
            });

            $from = str_replace('-', '', $filters['from']);
            $to   = str_replace('-', '', $filters['to']);
            $fileName = "cham-cong-{$from}-{$to}.xlsx";

            return Excel::download(
                new class($rows) implements
                    \Maatwebsite\Excel\Concerns\FromCollection,
                    \Maatwebsite\Excel\Concerns\WithHeadings,
                    \Maatwebsite\Excel\Concerns\ShouldAutoSize {

                    public function __construct(private Collection $rows) {}

                    public function collection(): Collection
                    {
                        return $this->rows->values();
                    }

                    public function headings(): array
                    {
                        return [
                            'Nhân viên',
                            'Loại',
                            'Ngày',
                            'Giờ',
                            'Địa điểm',
                            'Trong vùng',
                            'Khoảng cách (m)',
                            'Thiết bị',
                            'IP',
                            'Mô tả',
                        ];
                    }
                },
                $fileName
            );
        } catch (Throwable $e) {
            return $this->respond(
                false,
                'SERVER_ERROR',
                config('app.debug') ? ['message' => $e->getMessage()] : ['message' => 'Xuất Excel thất bại.'],
                500
            );
        }
    }

    private function resolveFilters(Request $request): array|\Illuminate\Http\JsonResponse
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

        $to      = $request->input('to') ?: now()->toDateString();
        $from    = $request->input('from') ?: Carbon::parse($to)->subDays(30)->toDateString();

        return [
            'user_id'  => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'from'     => $from,
            'to'       => $to,
            'page'     => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 20),
            'type'     => $request->input('type'),
            'within'   => $request->has('within') ? (int) $request->input('within') : null,
            'order'    => strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function buildQuery(array $filters)
    {
        $query = ChamCong::query()
            ->valid()
            ->between($filters['from'] . ' 00:00:00', $filters['to'] . ' 23:59:59')
            ->with([
                'user:id,name,email',
                'workpoint:id,ten',
            ]);

        if ($filters['user_id']) {
            $query->ofUser($filters['user_id']);
        }

        if ($filters['type']) {
            $query->{$filters['type']}();
        }

        if ($filters['within'] !== null) {
            $query->where('within_geofence', (bool) $filters['within']);
        }

        if ($filters['order'] === 'asc') {
            $query->orderBy('checked_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('checked_at', 'desc')->orderBy('id', 'desc');
        }

        return $query;
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
