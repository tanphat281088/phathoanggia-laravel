<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\DiemLamViec;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class WorkpointAdminController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->canManage($user)) {
            return $this->failed(['message' => 'Bạn không có quyền quản lý địa điểm.'], 'FORBIDDEN', 403);
        }

        $v = Validator::make($request->all(), [
            'q'        => ['nullable', 'string', 'max:255'],
            'type'     => ['nullable', 'in:fixed,event'],
            'status'   => ['nullable', 'in:0,1'],
            'expired'  => ['nullable', 'in:0,1'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $q       = trim((string) $request->input('q', ''));
        $type    = $request->input('type');
        $status  = $request->has('status') ? (int) $request->input('status') : null;
        $expired = $request->has('expired') ? (int) $request->input('expired') : null;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = DiemLamViec::query()
            ->with(['creator:id,name,email'])
            ->withCount('chamCongs');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('ten', 'like', '%' . $q . '%')
                    ->orWhere('ma_dia_diem', 'like', '%' . $q . '%')
                    ->orWhere('dia_chi', 'like', '%' . $q . '%');
            });
        }

        if ($type) {
            $query->where('loai_dia_diem', $type);
        }

        if ($status !== null) {
            $query->where('trang_thai', $status);
        }

        if ($expired !== null) {
            if ($expired === 1) {
                $query->whereNotNull('hieu_luc_den')->where('hieu_luc_den', '<', now());
            } else {
                $query->where(function ($qq) {
                    $qq->whereNull('hieu_luc_den')->orWhere('hieu_luc_den', '>=', now());
                });
            }
        }

        $query
            ->orderByRaw("CASE WHEN loai_dia_diem = 'fixed' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('trang_thai')
            ->orderBy('ten');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (DiemLamViec $wp) use ($user) {
            return $this->toItem($wp, $user);
        });

        return $this->success([
            'filter' => [
                'q'        => $q !== '' ? $q : null,
                'type'     => $type,
                'status'   => $status,
                'expired'  => $expired,
                'page'     => $page,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'items' => $items,
            'role'  => [
                'admin'   => $this->isAdmin($user),
                'manager' => $this->canManage($user),
            ],
        ], 'WORKPOINT_ADMIN_LIST');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->isAdmin($user)) {
            return $this->failed(['message' => 'Chỉ quản trị viên mới được thêm địa điểm.'], 'FORBIDDEN', 403);
        }

        $v = Validator::make($request->all(), [
            'ma_dia_diem'   => ['nullable', 'string', 'max:50', 'unique:diem_lam_viecs,ma_dia_diem'],
            'ten'           => ['required', 'string', 'max:255'],
            'loai_dia_diem' => ['nullable', 'in:fixed,event'],
            'lat'           => ['required', 'numeric', 'between:-90,90'],
            'lng'           => ['required', 'numeric', 'between:-180,180'],
            'ban_kinh_m'    => ['required', 'integer', 'min:30', 'max:5000'],
            'dia_chi'       => ['nullable', 'string', 'max:255'],
            'ghi_chu'       => ['nullable', 'string', 'max:2000'],
            'trang_thai'    => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $type = $request->input('loai_dia_diem', 'fixed');

        $wp = new DiemLamViec();
        $wp->ma_dia_diem   = trim((string) $request->input('ma_dia_diem')) ?: $this->makeCode($type);
        $wp->ten           = trim((string) $request->input('ten'));
        $wp->loai_dia_diem = $type;
        $wp->nguon_tao     = DiemLamViec::SOURCE_MANUAL;
        $wp->created_by    = $user->id;
        $wp->dia_chi       = trim((string) $request->input('dia_chi')) ?: null;
        $wp->ghi_chu       = trim((string) $request->input('ghi_chu')) ?: null;
        $wp->lat           = (float) $request->input('lat');
        $wp->lng           = (float) $request->input('lng');
        $wp->ban_kinh_m    = (int) $request->input('ban_kinh_m');
        $wp->trang_thai    = $request->has('trang_thai') ? (int) $request->boolean('trang_thai') : 1;
        $wp->save();

        $wp->load(['creator:id,name,email'])->loadCount('chamCongs');

        return $this->success([
            'item' => $this->toItem($wp, $user),
        ], 'WORKPOINT_ADMIN_CREATED', 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user || !$this->isAdmin($user)) {
            return $this->failed(['message' => 'Chỉ quản trị viên mới được sửa địa điểm.'], 'FORBIDDEN', 403);
        }

        $wp = DiemLamViec::query()->find($id);
        if (!$wp) {
            return $this->failed(['message' => 'Không tìm thấy địa điểm.'], 'NOT_FOUND', 404);
        }

        $v = Validator::make($request->all(), [
            'ma_dia_diem'   => ['nullable', 'string', 'max:50', 'unique:diem_lam_viecs,ma_dia_diem,' . $id],
            'ten'           => ['required', 'string', 'max:255'],
            'loai_dia_diem' => ['nullable', 'in:fixed,event'],
            'lat'           => ['required', 'numeric', 'between:-90,90'],
            'lng'           => ['required', 'numeric', 'between:-180,180'],
            'ban_kinh_m'    => ['required', 'integer', 'min:30', 'max:5000'],
            'dia_chi'       => ['nullable', 'string', 'max:255'],
            'ghi_chu'       => ['nullable', 'string', 'max:2000'],
            'trang_thai'    => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $wp->ma_dia_diem   = trim((string) $request->input('ma_dia_diem')) ?: $wp->ma_dia_diem ?: $this->makeCode($request->input('loai_dia_diem', $wp->loai_dia_diem ?: 'fixed'));
        $wp->ten           = trim((string) $request->input('ten'));
        $wp->loai_dia_diem = $request->input('loai_dia_diem', $wp->loai_dia_diem ?: 'fixed');
        $wp->dia_chi       = trim((string) $request->input('dia_chi')) ?: null;
        $wp->ghi_chu       = trim((string) $request->input('ghi_chu')) ?: null;
        $wp->lat           = (float) $request->input('lat');
        $wp->lng           = (float) $request->input('lng');
        $wp->ban_kinh_m    = (int) $request->input('ban_kinh_m');
        $wp->trang_thai    = $request->has('trang_thai') ? (int) $request->boolean('trang_thai') : (int) $wp->trang_thai;
        $wp->save();

        $wp->load(['creator:id,name,email'])->loadCount('chamCongs');

        return $this->success([
            'item' => $this->toItem($wp, $user),
        ], 'WORKPOINT_ADMIN_UPDATED');
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user || !$this->canManage($user)) {
            return $this->failed(['message' => 'Bạn không có quyền xóa/ẩn địa điểm.'], 'FORBIDDEN', 403);
        }

        $wp = DiemLamViec::query()->withCount('chamCongs')->find($id);
        if (!$wp) {
            return $this->failed(['message' => 'Không tìm thấy địa điểm.'], 'NOT_FOUND', 404);
        }

        if (!$this->canDeletePoint($user, $wp)) {
            return $this->failed([
                'message' => 'Quản lý chỉ được dọn các địa điểm sự kiện tạo từ điện thoại; quản trị viên mới được thao tác toàn bộ.'
            ], 'FORBIDDEN', 403);
        }

        $hasLogs = (int) ($wp->cham_congs_count ?? 0) > 0;

        if ($hasLogs) {
            $note = trim((string) $wp->ghi_chu);
            $append = 'AUTO_ARCHIVED ' . now()->format('Y-m-d H:i');
            $wp->ghi_chu = $note ? ($note . ' | ' . $append) : $append;
            $wp->trang_thai = 0;
            if (is_null($wp->hieu_luc_den) || Carbon::parse($wp->hieu_luc_den)->gt(now())) {
                $wp->hieu_luc_den = now();
            }
            $wp->save();

            return $this->success([
                'mode' => 'archived',
                'message' => 'Địa điểm đã phát sinh log nên hệ thống đã ẩn thay vì xóa cứng.',
            ], 'WORKPOINT_ADMIN_ARCHIVED');
        }

        $wp->delete();

        return $this->success([
            'mode' => 'deleted',
            'message' => 'Đã xóa địa điểm thành công.',
        ], 'WORKPOINT_ADMIN_DELETED');
    }

    private function toItem(DiemLamViec $wp, $user): array
    {
        $creatorName = null;
        if ($wp->relationLoaded('creator') && $wp->creator) {
            $creatorName = $wp->creator->name ?? $wp->creator->email ?? ('#' . $wp->creator->id);
        }

        $count = (int) ($wp->cham_congs_count ?? 0);

        return [
            'id'               => (int) $wp->id,
            'ma_dia_diem'      => $wp->ma_dia_diem,
            'ten'              => $wp->ten,
            'loai_dia_diem'    => $wp->loai_dia_diem,
            'loai_label'       => $wp->typeLabel(),
            'nguon_tao'        => $wp->nguon_tao,
            'created_by'       => $wp->created_by,
            'created_by_name'  => $creatorName,
            'dia_chi'          => $wp->dia_chi,
            'ghi_chu'          => $wp->ghi_chu,
            'lat'              => (float) $wp->lat,
            'lng'              => (float) $wp->lng,
            'ban_kinh_m'       => (int) $wp->ban_kinh_m,
            'trang_thai'       => (int) $wp->trang_thai,
            'trang_thai_label' => (int) $wp->trang_thai === 1 ? 'Đang dùng' : 'Đã ẩn',
            'hieu_luc_tu'      => $wp->hieu_luc_tu?->toDateTimeString(),
            'hieu_luc_den'     => $wp->hieu_luc_den?->toDateTimeString(),
            'available_now'    => $wp->isAvailableAt(now()),
            'expired'          => $wp->isExpired(now()),
            'cham_congs_count' => $count,
            'can_delete'       => $this->canDeletePoint($user, $wp),
            'delete_mode'      => $count > 0 ? 'archive' : 'delete',
        ];
    }

    private function canManage($user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }

    private function canDeletePoint($user, DiemLamViec $wp): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isManager($user)
            && $wp->loai_dia_diem === DiemLamViec::TYPE_EVENT
            && $wp->nguon_tao === DiemLamViec::SOURCE_MOBILE;
    }

    private function isAdmin($user): bool
    {
        if (!$user) return false;

        if (strtolower((string) $user->email) === 'admin@gmail.com') {
            return true;
        }

        $code = $this->roleCode($user);

        return $code === 'super_admin'
            || $code === 'admin'
            || str_contains($code, 'admin');
    }

    private function isManager($user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $code = $this->roleCode($user);

        return $code === 'quan_ly'
            || $code === 'quanly'
            || $code === 'manager'
            || str_contains($code, 'quan_ly')
            || str_contains($code, 'manager');
    }

    private function roleCode($user): string
    {
        try {
            $user->loadMissing('vaiTro');
        } catch (\Throwable $e) {
        }

        $raw = (string) (
            $user?->vaiTro?->ma_vai_tro
            ?? $user?->vaiTro?->ma
            ?? $user?->vaiTro?->code
            ?? $user?->vaiTro?->ten
            ?? $user?->vaiTro?->slug
            ?? $user?->vaiTro?->name
            ?? ''
        );

        return (string) Str::of($raw)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function makeCode(string $type): string
    {
        $prefix = $type === DiemLamViec::TYPE_EVENT ? 'EVA' : 'FIX';

        do {
            $code = $prefix . now()->format('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        } while (DiemLamViec::query()->where('ma_dia_diem', $code)->exists());

        return $code;
    }

    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code)->setStatusCode($status);
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
            return \App\Class\CustomResponse::failed($data, $code)->setStatusCode($status);
        }

        return response()->json([
            'success' => false,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }
}
