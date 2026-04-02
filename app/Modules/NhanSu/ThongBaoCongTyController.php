<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ThongBaoCongTy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ThongBaoCongTyController extends BaseController
{
    public function index(Request $request)
    {
        $v = Validator::make($request->all(), [
            'q'        => ['nullable', 'string', 'max:255'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $q       = trim((string) $request->input('q', ''));
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        $query = $this->baseQuery()
            ->visible();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('tieu_de', 'like', "%{$q}%")
                  ->orWhere('tom_tat', 'like', "%{$q}%")
                  ->orWhere('noi_dung', 'like', "%{$q}%");
            });
        }

        $query->orderByDesc('ghim_dau')
            ->orderByRaw('COALESCE(publish_at, created_at) DESC')
            ->orderByDesc('id');

        return $this->success(
            $this->paginatePayload($query, $page, $perPage, false),
            'COMPANY_NOTICE_LIST'
        );
    }

    public function show(int $id)
    {
        $row = $this->baseQuery()
            ->visible()
            ->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        return $this->success([
            'item' => $this->toApi($row, false),
        ], 'COMPANY_NOTICE_SHOW');
    }

    public function download(int $id)
    {
        $row = ThongBaoCongTy::query()
            ->visible()
            ->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        return $this->serveAttachment($row);
    }

    public function adminIndex(Request $request)
    {
        $v = Validator::make($request->all(), [
            'q'         => ['nullable', 'string', 'max:255'],
            'trang_thai'=> ['nullable', 'in:all,draft,published,archived'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $q       = trim((string) $request->input('q', ''));
        $status  = (string) $request->input('trang_thai', 'all');
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = $this->baseQuery();

        if ($status !== 'all') {
            $query->where('trang_thai', $status);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('tieu_de', 'like', "%{$q}%")
                  ->orWhere('tom_tat', 'like', "%{$q}%")
                  ->orWhere('noi_dung', 'like', "%{$q}%");
            });
        }

        $query->orderByDesc('ghim_dau')
            ->orderByRaw('COALESCE(publish_at, created_at) DESC')
            ->orderByDesc('id');

        return $this->success(
            $this->paginatePayload($query, $page, $perPage, true),
            'COMPANY_NOTICE_ADMIN_LIST'
        );
    }

    public function adminShow(int $id)
    {
        $row = $this->baseQuery()->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        return $this->success([
            'item' => $this->toApi($row, true),
        ], 'COMPANY_NOTICE_ADMIN_SHOW');
    }

    public function adminDownload(int $id)
    {
        $row = ThongBaoCongTy::query()->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        return $this->serveAttachment($row);
    }

    public function store(Request $request)
    {
        $v = $this->validateUpsert($request);
        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $uid = $request->user()?->id ?? auth()->id();

        $row = new ThongBaoCongTy();
        $this->fillRow($row, $request);

        if ($uid) {
            $row->created_by = $uid;
            $row->updated_by = $uid;
        }

        if ($request->hasFile('attachment')) {
            $this->replaceAttachment($row, $request);
        }

        $row->save();
        $row->load(['creator:id,name,email', 'updater:id,name,email']);

        return $this->success([
            'item' => $this->toApi($row, true),
        ], 'COMPANY_NOTICE_CREATED', 201);
    }

    public function update(Request $request, int $id)
    {
        $row = ThongBaoCongTy::query()->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        $v = $this->validateUpsert($request);
        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $uid = $request->user()?->id ?? auth()->id();

        $this->fillRow($row, $request);

        if ($uid) {
            $row->updated_by = $uid;
        }

        if ($request->hasFile('attachment')) {
            $this->replaceAttachment($row, $request);
        }

        $row->save();
        $row->load(['creator:id,name,email', 'updater:id,name,email']);

        return $this->success([
            'item' => $this->toApi($row, true),
        ], 'COMPANY_NOTICE_UPDATED');
    }

    public function destroy(int $id)
    {
        $row = ThongBaoCongTy::query()->find($id);

        if (!$row) {
            return $this->failed([], 'NOT_FOUND', 404);
        }

        $row->delete();

        return $this->success([], 'COMPANY_NOTICE_DELETED');
    }

    private function validateUpsert(Request $request)
    {
        return Validator::make($request->all(), [
            'tieu_de'     => ['required', 'string', 'max:255'],
            'tom_tat'     => ['nullable', 'string', 'max:500'],
            'noi_dung'    => ['nullable', 'string'],
            'trang_thai'  => ['required', 'in:draft,published,archived'],
            'ghim_dau'    => ['nullable', 'boolean'],
            'publish_at'  => ['nullable', 'date'],
            'expires_at'  => ['nullable', 'date', 'after:publish_at'],
            'attachment'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }

    private function baseQuery()
    {
        return ThongBaoCongTy::query()->with([
            'creator:id,name,email',
            'updater:id,name,email',
        ]);
    }

    private function fillRow(ThongBaoCongTy $row, Request $request): void
    {
        $status = (string) $request->input('trang_thai', $row->trang_thai ?: 'draft');

        $publishAt = $request->filled('publish_at')
            ? Carbon::parse((string) $request->input('publish_at'))
            : $row->publish_at;

        if ($status === 'published' && !$publishAt) {
            $publishAt = now();
        }

        $expiresAt = $request->filled('expires_at')
            ? Carbon::parse((string) $request->input('expires_at'))
            : $row->expires_at;

        $row->tieu_de    = trim((string) $request->input('tieu_de', $row->tieu_de));
        $row->tom_tat    = $request->filled('tom_tat') ? trim((string) $request->input('tom_tat')) : null;
        $row->noi_dung   = $request->filled('noi_dung') ? (string) $request->input('noi_dung') : null;
        $row->trang_thai = $status;
        $row->ghim_dau   = $request->boolean('ghim_dau', (bool) $row->ghim_dau);
        $row->publish_at = $publishAt;
        $row->expires_at = $expiresAt;
    }

    private function replaceAttachment(ThongBaoCongTy $row, Request $request): void
    {
        if (!$request->hasFile('attachment')) {
            return;
        }

        if ($row->attachment_path && Storage::disk('local')->exists($row->attachment_path)) {
            Storage::disk('local')->delete($row->attachment_path);
        }

        $file = $request->file('attachment');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $dir  = 'company-announcements/' . now()->format('Y/m');
        $name = uniqid('notice_', true) . '.' . $ext;
        $path = $file->storeAs($dir, $name, 'local');

        $row->attachment_original_name = $file->getClientOriginalName();
        $row->attachment_path          = $path;
        $row->attachment_mime          = $file->getMimeType() ?: $file->getClientMimeType();
        $row->attachment_size          = $file->getSize();
    }

    private function serveAttachment(ThongBaoCongTy $row)
    {
        if (!$row->attachment_path) {
            return $this->failed([], 'FILE_NOT_FOUND', 404);
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($row->attachment_path)) {
            return $this->failed([], 'FILE_NOT_FOUND', 404);
        }

        $full = $disk->path($row->attachment_path);
        $mime = $row->attachment_mime ?: (mime_content_type($full) ?: 'application/octet-stream');

        return response()->file($full, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function paginatePayload($query, int $page, int $perPage, bool $admin = false): array
    {
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (ThongBaoCongTy $row) => $this->toApi($row, $admin))
            ->values();

        return [
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'items' => $items,
        ];
    }

    private function toApi(ThongBaoCongTy $row, bool $admin = false): array
    {
        $creatorName = null;
        if ($row->relationLoaded('creator') && $row->creator) {
            $creatorName = $row->creator->name ?? $row->creator->email ?? null;
        }

        $updaterName = null;
        if ($row->relationLoaded('updater') && $row->updater) {
            $updaterName = $row->updater->name ?? $row->updater->email ?? null;
        }

        return [
            'id'                => $row->id,
            'tieu_de'           => $row->tieu_de,
            'tom_tat'           => $row->tom_tat,
            'noi_dung'          => $row->noi_dung,
            'trang_thai'        => $row->trang_thai,
            'ghim_dau'          => (bool) $row->ghim_dau,
            'publish_at'        => optional($row->publish_at)->toDateTimeString(),
            'expires_at'        => optional($row->expires_at)->toDateTimeString(),
            'has_attachment'    => !empty($row->attachment_path),
            'attachment_name'   => $row->attachment_original_name,
            'attachment_mime'   => $row->attachment_mime,
            'attachment_size'   => $row->attachment_size,
            'attachment_endpoint' => !empty($row->attachment_path)
                ? ($admin
                    ? "/api/nhan-su/thong-bao-admin-file/{$row->id}"
                    : "/api/nhan-su/thong-bao-file/{$row->id}")
                : null,
            'created_by'        => $row->created_by,
            'updated_by'        => $row->updated_by,
            'created_by_name'   => $creatorName,
            'updated_by_name'   => $updaterName,
            'created_at'        => optional($row->created_at)->toDateTimeString(),
            'updated_at'        => optional($row->updated_at)->toDateTimeString(),
        ];
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
