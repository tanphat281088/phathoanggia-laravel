<?php

namespace App\Services\Face;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FaceVerifyService
{
    /**
     * Verify khuôn mặt cho 1 nhân viên bằng ảnh (binary).
     *
     * @param  string $employeeKey   Mã nhân viên / khoá nhận diện (VD: 'NV001' hoặc user_id cast string).
     * @param  string $imageBinary   Nội dung ảnh dạng binary (raw bytes).
     * @return array                 ['ok' => bool, 'score' => int, 'provider' => string, 'raw' => mixed]
     *
     * @throws \RuntimeException     Khi cấu hình/endpoint lỗi, hoặc HTTP error.
     */
    public function verify(string $employeeKey, string $imageBinary): array
    {
        $provider = config('face.provider', 'aws-gateway');

        if ($provider === 'aws-gateway') {
            return $this->verifyViaAwsGateway($employeeKey, $imageBinary);
        }

        // Future: thêm nhánh 'rekognition' nếu dùng SDK trực tiếp
        throw new RuntimeException('Face provider [' . $provider . '] chưa được hỗ trợ.');
    }

    /**
     * Verify từ UploadedFile (tiện cho Controller dùng trực tiếp file upload).
     */
    public function verifyUploaded(string $employeeKey, \Illuminate\Http\UploadedFile $file): array
    {
        $binary = file_get_contents($file->getRealPath());

        return $this->verify($employeeKey, $binary);
    }

    /**
     * Provider: AWS API Gateway (Lambda + Rekognition)
     * Payload tương thích với Apps Script cũ:
     *   { employeeId: emp, imageBase64: "...", threshold: 90 }
     */
    protected function verifyViaAwsGateway(string $employeeKey, string $imageBinary): array
    {
        $cfg = config('face.aws_gateway', []);
        $url = Arr::get($cfg, 'url');
        $timeout = (float) Arr::get($cfg, 'timeout', 3.0);
        $threshold = (int) Arr::get($cfg, 'threshold', 90);

        if (empty($url)) {
            throw new RuntimeException('FACE_AWS_GATEWAY_URL chưa được cấu hình.');
        }

        // Giới hạn kích thước ảnh (đơn vị: bytes)
        $maxBytes = (int) config('face.image.max_bytes', 800 * 1024);
        $len = strlen($imageBinary);
        if ($len <= 0) {
            throw new RuntimeException('Ảnh khuôn mặt trống (size = 0).');
        }
        if ($len > $maxBytes) {
            throw new RuntimeException('Ảnh khuôn mặt quá lớn (>' . $maxBytes . ' bytes). Vui lòng chụp lại.');
        }

        $imageBase64 = base64_encode($imageBinary);

        $payload = [
            'employeeId'  => $employeeKey,
            'imageBase64' => $imageBase64,
            'threshold'   => $threshold,
        ];

        $logChannel   = config('face.log.channel', 'stack');
        $logSuccess   = (bool) config('face.log.log_success', false);
        $logFail      = (bool) config('face.log.log_fail', true);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $status = $response->status();
            $body   = $response->json();

            $okHttp = $response->successful();

            // Nếu HTTP lỗi (4xx/5xx)
            if (!$okHttp) {
                if ($logFail) {
                    Log::channel($logChannel)->warning('FaceVerify AWS-GW HTTP fail', [
                        'employee' => $employeeKey,
                        'status'   => $status,
                        'body'     => $response->body(),
                    ]);
                }

                throw new RuntimeException('Lỗi nhận diện khuôn mặt (HTTP ' . $status . ').');
            }

            // Chuẩn hoá kết quả từ Lambda
            $ok    = (bool) ($body['ok'] ?? false);
            $score = (int)  ($body['score'] ?? 0);

            // Log theo cấu hình
            if ($ok && $logSuccess) {
                Log::channel($logChannel)->info('FaceVerify OK', [
                    'employee' => $employeeKey,
                    'score'    => $score,
                ]);
            } elseif (!$ok && $logFail) {
                Log::channel($logChannel)->warning('FaceVerify FAIL', [
                    'employee' => $employeeKey,
                    'score'    => $score,
                    'body'     => $body,
                ]);
            }

            return [
                'ok'       => $ok && $score >= $threshold,
                'score'    => $score,
                'provider' => 'aws-gateway',
                'raw'      => $body,
            ];
        } catch (\Throwable $e) {
            if ($logFail) {
                Log::channel($logChannel)->error('FaceVerify exception', [
                    'employee' => $employeeKey,
                    'error'    => $e->getMessage(),
                ]);
            }

            throw new RuntimeException('Không thể kết nối dịch vụ nhận diện khuôn mặt: ' . $e->getMessage(), 0, $e);
        }
    }
}
