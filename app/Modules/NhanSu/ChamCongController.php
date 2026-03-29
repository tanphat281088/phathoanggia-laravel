<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ChamCong;
use App\Models\DiemLamViec;
use App\Services\Face\FaceVerifyService;
use App\Services\Timesheet\BangCongService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ChamCongController extends BaseController
{
    /**
     * POST /nhan-su/cham-cong/checkin
     *
     * Mô hình mới:
     * - KHÔNG còn chặn "mỗi ngày chỉ 1 check-in"
     * - Chỉ chặn khi user đang có 1 PHIÊN MỞ (đã check-in nhưng chưa check-out)
     * - Nếu bấm lại trong ~2 phút ở cùng địa điểm -> trả success idempotent
     * - Ưu tiên workpoint_id do client chọn; nếu không có thì fallback nearest
     */
    public function checkin(Request $request, FaceVerifyService $face)
    {
        $user = $request->user();
        $userId = $user?->id ?? auth()->id();

        if (!$userId) {
            return $this->respond(false, 'UNAUTHORIZED', null, 401);
        }

        $v = Validator::make($request->all(), [
            'lat'               => ['required', 'numeric', 'between:-90,90'],
            'lng'               => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m'        => ['nullable', 'integer', 'min:0'],
            'device_id'         => ['nullable', 'string', 'max:100'],

            // FE nên gửi workpoint_id; nếu không có thì BE fallback nearest để tương thích ngược
            'workpoint_id'      => ['nullable', 'integer', 'min:1'],

            // Ảnh selfie
            'face_image'        => ['nullable', 'file', 'image', 'max:4096'],
            'face_image_base64' => ['nullable', 'string'],
        ], [], [
            'lat' => 'lat',
            'lng' => 'lng',
            'workpoint_id' => 'Địa điểm chấm công',
        ]);

        if ($v->fails()) {
            return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
        }

        $hasFile = $request->hasFile('face_image');
        $hasB64  = $request->filled('face_image_base64');

        if (!$hasFile && !$hasB64) {
            return $this->respond(false, 'VALIDATION_ERROR', [
                'face_image' => ['Thiếu ảnh selfie. Vui lòng chụp bằng camera.'],
            ], 422);
        }

        $lat      = (float) $request->input('lat');
        $lng      = (float) $request->input('lng');
        $accuracy = $request->input('accuracy_m');
        $deviceId = $request->input('device_id') ?: $request->header('X-Device-ID') ?: 'WEB';
        $clientIp = $request->ip();
        $workpointId = $request->filled('workpoint_id') ? (int) $request->input('workpoint_id') : null;

        // Chỉ cho phép từ mobile
        if (strtoupper((string) $deviceId) !== 'MOBILE') {
            return $this->respond(false, 'DEVICE_NOT_ALLOWED', [
                'message' => 'Chỉ cho phép chấm công từ ứng dụng di động.',
            ], 403);
        }

        $now = Carbon::now(config('app.timezone'));

        // ===== 1) Resolve workpoint =====
        $diem = $this->resolveWorkpoint($workpointId, $lat, $lng);
        if (!$diem) {
            return $this->respond(false, 'NO_WORKPOINT', [
                'message' => 'Chưa cấu hình điểm làm việc (geofence).',
            ], 503);
        }

        [$within, $distanceM] = $diem->withinGeofence($lat, $lng);

        if (!$within) {
            $payload = [
                'message'    => 'Chỉ cho phép chấm công tại khu vực sự kiện/công ty.',
                'distance_m' => (int) $distanceM,
                'ban_kinh_m' => (int) $diem->ban_kinh_m,
                'workpoint'  => [
                    'id'  => (int) $diem->id,
                    'ten' => $diem->ten,
                ],
            ];

            $code = $workpointId ? 'OUT_OF_GEOFENCE_SELECTED' : 'OUT_OF_GEOFENCE';

            return $this->respond(false, $code, $payload, 403);
        }

        // ===== 2) Không chặn theo "đã check-in hôm nay" nữa
        // Chỉ chặn nếu đang có 1 PHIÊN MỞ (check-in gần nhất chưa có checkout sau nó)
        $openCheckin = $this->findOpenSession($userId);

        if ($openCheckin) {
            $sameWorkpoint = (int) ($openCheckin->workpoint_id ?? 0) === (int) $diem->id;
            $recentSameTap = $sameWorkpoint
                && $openCheckin->checked_at
                && $openCheckin->checked_at->gte($now->copy()->subMinutes(2));

            // Idempotent mềm: bấm lại cùng điểm trong 2 phút thì trả success luôn
            if ($recentSameTap) {
                return $this->respond(true, 'CHECKIN_OK', [
                    'log' => [
                        'id'         => $openCheckin->id,
                        'desc'       => $openCheckin->shortDesc(),
                        'checked_at' => $openCheckin->checked_at,
                        'distance_m' => $openCheckin->distance_m,
                        'within'     => (bool) $openCheckin->within_geofence,
                        'face_score' => (int) ($openCheckin->face_score ?? 0),
                    ],
                    'workpoint' => [
                        'id'         => (int) ($openCheckin->workpoint_id ?: $diem->id),
                        'ten'        => $openCheckin->workpoint?->ten ?? $diem->ten,
                        'ban_kinh_m' => (int) ($openCheckin->workpoint?->ban_kinh_m ?? $diem->ban_kinh_m),
                    ],
                    'session' => [
                        'open'        => true,
                        'existing'    => true,
                        'checked_in_at' => optional($openCheckin->checked_at)->toDateTimeString(),
                    ],
                ], 200);
            }

            return $this->respond(false, 'OPEN_SESSION_EXISTS', [
                'message' => 'Bạn đang có một phiên làm việc chưa chấm công ra. Vui lòng chấm công ra trước khi vào địa điểm mới.',
                'open_session' => [
                    'id'          => (int) $openCheckin->id,
                    'checked_at'  => optional($openCheckin->checked_at)->toDateTimeString(),
                    'workpoint_id'=> (int) ($openCheckin->workpoint_id ?? 0),
                    'workpoint_ten' => $openCheckin->workpoint?->ten,
                    'short_desc'  => $openCheckin->shortDesc(),
                ],
                'selected_workpoint' => [
                    'id'  => (int) $diem->id,
                    'ten' => $diem->ten,
                ],
            ], 409);
        }

        try {
            // ===== 3) Chuẩn hoá ảnh về binary =====
            $binary = null;

            if ($hasFile) {
                /** @var \Illuminate\Http\UploadedFile $file */
                $file   = $request->file('face_image');
                $binary = file_get_contents($file->getRealPath());
            } else {
                $b64 = (string) $request->input('face_image_base64');
                if (str_contains($b64, ',')) {
                    $b64 = explode(',', $b64, 2)[1];
                }
                $binary = base64_decode($b64, true);

                if ($binary === false) {
                    return $this->respond(false, 'INVALID_FACE_BASE64', [
                        'message' => 'Ảnh selfie (base64) không hợp lệ.',
                    ], 422);
                }
            }

            $employeeKey = $user->ma_nv;
            if (!$employeeKey) {
                return $this->respond(false, 'MISSING_EMPLOYEE_CODE', [
                    'message' => 'Tài khoản chưa được cấu hình Mã nhân viên (ma_nv). Vui lòng liên hệ quản trị.',
                ], 422);
            }

            // ===== 4) Gọi FaceVerify với SOFT-FAIL =====
            $faceOk    = null; // null = service lỗi / không gọi được
            $faceScore = 0;
            $provider  = null;
            $faceError = null;

            try {
                $faceResult = $face->verify($employeeKey, $binary);
                $faceOk     = (bool) ($faceResult['ok'] ?? false);
                $faceScore  = (int) ($faceResult['score'] ?? 0);
                $provider   = (string) ($faceResult['provider'] ?? 'aws-gateway');

                if ($faceOk === false) {
                    return $this->respond(false, 'FACE_NOT_MATCH', [
                        'message' => 'Không khớp khuôn mặt. Vui lòng chấm công lại.',
                        'score'   => $faceScore,
                    ], 409);
                }
            } catch (Throwable $fe) {
                $faceError = $fe->getMessage();

                \Log::error('FaceVerify exception (soft-fail)', [
                    'employee' => $employeeKey,
                    'error'    => $faceError,
                ]);
            }

            // ===== 5) Lưu ảnh =====
            $disk = Storage::disk('public');
            $dir  = 'attendance/' . $userId;
            $disk->makeDirectory($dir);

            $filename = $now->format('Ymd_His') . '_' . uniqid('face_', true) . '.jpg';
            $path     = $dir . '/' . $filename;
            $disk->put($path, $binary);

            // ===== 6) Tạo log check-in mới =====
            $log = null;

            DB::transaction(function () use (
                &$log,
                $userId,
                $diem,
                $lat,
                $lng,
                $accuracy,
                $distanceM,
                $deviceId,
                $clientIp,
                $now,
                $path,
                $faceOk,
                $faceScore,
                $provider,
                $faceError
            ) {
                $log = ChamCong::create([
                    'user_id'         => $userId,
                    'workpoint_id'    => $diem->id,
                    'type'            => 'checkin',
                    'lat'             => $lat,
                    'lng'             => $lng,
                    'accuracy_m'      => $accuracy,
                    'distance_m'      => $distanceM,
                    'within_geofence' => 1,
                    'device_id'       => $deviceId,
                    'ip'              => $clientIp,
                    'checked_at'      => $now,
                    'ghi_chu'         => null,

                    'selfie_path'     => $path,
                    'face_match'      => is_null($faceOk) ? null : $faceOk,
                    'face_score'      => $faceScore,
                    'face_provider'   => $provider,
                    'face_checked_at' => $faceOk === null ? null : $now,
                    'reason'          => $faceError ? ('FACE_SERVICE_ERROR: ' . $faceError) : null,
                    'cancelled'       => false,
                    'cancelled_at'    => null,
                ]);
            });

            // ===== 7) Recompute bảng công hiện tại (giữ hành vi cũ) =====
            $timesheetRecomputed = false;

            try {
                /** @var BangCongService $svc */
                $svc = app(BangCongService::class);
                $ym  = BangCongService::cycleLabelForDate($now);
                $svc->computeMonth($ym, (int) $userId);
                $timesheetRecomputed = true;
            } catch (Throwable $e) {
                \Log::warning('CHECKIN recompute FAIL', [
                    'uid' => $userId,
                    'err' => $e->getMessage(),
                ]);
            }

            $respData = [
                'log' => [
                    'id'         => $log->id,
                    'desc'       => $log->shortDesc(),
                    'checked_at' => $log->checked_at,
                    'distance_m' => $log->distance_m,
                    'within'     => (bool) $log->within_geofence,
                    'face_score' => $faceScore,
                    'face_ok'    => $faceOk,
                    'face_error' => $faceError,
                ],
                'workpoint' => [
                    'id'         => (int) $diem->id,
                    'ten'        => $diem->ten,
                    'ban_kinh_m' => (int) $diem->ban_kinh_m,
                ],
                'session' => [
                    'open'         => true,
                    'existing'     => false,
                    'checked_in_at'=> optional($log->checked_at)->toDateTimeString(),
                ],
            ];

            if (config('app.debug')) {
                $respData['debug'] = [
                    'timesheet_recomputed' => $timesheetRecomputed,
                    'face_provider'        => $provider,
                ];
            }

            return $this->respond(true, 'CHECKIN_OK', $respData, 201);
        } catch (Throwable $e) {
            \Log::error('CHECKIN_ERROR', [
                'uid' => $userId,
                'err' => $e->getMessage(),
            ]);

            return $this->respond(
                false,
                'SERVER_ERROR',
                ['message' => config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống khi chấm công. Vui lòng thử lại.'],
                500
            );
        }
    }

    /**
     * Tìm phiên đang mở:
     * - lấy checkin hợp lệ gần nhất
     * - nếu chưa có checkout hợp lệ nào sau nó => đó là phiên đang mở
     */
    private function findOpenSession(int $userId): ?ChamCong
    {
        /** @var ChamCong|null $lastCheckin */
        $lastCheckin = ChamCong::query()
            ->with(['workpoint:id,ten,ban_kinh_m'])
            ->ofUser($userId)
            ->valid()
            ->checkin()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();

        if (!$lastCheckin) {
            return null;
        }

        $hasCheckoutAfter = ChamCong::query()
            ->ofUser($userId)
            ->valid()
            ->checkout()
            ->where('checked_at', '>', $lastCheckin->checked_at)
            ->exists();

        return $hasCheckoutAfter ? null : $lastCheckin;
    }

    /**
     * Ưu tiên workpoint do client chọn; nếu không có thì fallback nearest
     */
    private function resolveWorkpoint(?int $workpointId, float $lat, float $lng): ?DiemLamViec
    {
        if ($workpointId) {
            return DiemLamViec::query()->find($workpointId);
        }

        return DiemLamViec::nearest($lat, $lng);
    }

    /**
     * Chuẩn hoá response (tương thích CustomResponse nếu dự án có).
     * - Nếu $data là string và có $extra => tự bọc thành ['message' => ...] + extra
     * - Nếu $data là array và có $extra => merge luôn
     */
    private function respond(bool $success, string $code, $data = null, int $status = 200, array $extra = [])
    {
        $payload = $data;

        if (!empty($extra)) {
            if (is_array($payload)) {
                $payload = array_merge($payload, $extra);
            } elseif (is_null($payload)) {
                $payload = $extra;
            } else {
                $payload = array_merge(['message' => $payload], $extra);
            }
        }

        if (class_exists(\App\Class\CustomResponse::class)) {
            if ($success) {
                return \App\Class\CustomResponse::success($payload, $code)->setStatusCode($status);
            }

            return \App\Class\CustomResponse::failed($payload, $code)->setStatusCode($status);
        }

        return response()->json([
            'success' => $success,
            'code'    => $code,
            'data'    => $payload,
        ], $status);
    }
}