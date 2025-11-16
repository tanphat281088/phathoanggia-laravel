<?php

namespace App\Modules\Utilities\Zalo\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ZaloApiService
 * - /me (Social API – kiểm tra tối thiểu)
 * - Gửi tin nhắn OA thật (endpoint cấu hình qua .env)
 *
 * ENV cần:
 *  ZL_GRAPH_BASE=https://graph.zalo.me
 *  ZL_SEND_URL=https://openapi.zalo.me/v3.0/oa/message/cs     (Message V3 - CS)
 *  ZL_SEND_AUTH_HEADER=Authorization                           (hoặc 'access_token')
 *  ZL_OA_ACCESS_TOKEN=<token OA>
 */
class ZaloApiService
{
    /**
     * Gọi Social API /me để lấy id, name, picture (tham khảo).
     * Header: access_token: <token>  (Social API vẫn chấp nhận access_token ở header)
     */
    public function me(string $accessToken): array
    {
        $base = rtrim((string) env('ZL_GRAPH_BASE', 'https://graph.zalo.me'), '/');
        $url  = $base . '/v2.0/me';
        $qs   = 'fields=id,name,picture';

        try {
            $resp = Http::timeout(15)
                ->withHeaders([
                    'access_token' => $accessToken,
                ])
                ->get("$url?$qs");

            if (!$resp->ok()) {
                Log::warning('[ZL][API] /me http error', [
                    'status' => $resp->status(),
                    'body'   => $resp->body()
                ]);
                return ['error' => -1, 'message' => 'http error', 'status' => $resp->status()];
            }

            $j = $resp->json();
            return is_array($j) ? $j : ['error' => -1, 'message' => 'invalid json'];
        } catch (\Throwable $e) {
            Log::error('[ZL][API] /me exception', ['err' => $e->getMessage()]);
            return ['error' => -1, 'message' => 'exception', 'detail' => $e->getMessage()];
        }
    }

    /**
     * Gửi tin nhắn OA thật (Message V3 – CS).
     * Payload mặc định: { recipient: { user_id }, message: { text } }.
     * Header auth đọc từ .env:
     *  - ZL_SEND_AUTH_HEADER=Authorization  => sẽ tự prep "Bearer <token>"
     *  - ZL_SEND_AUTH_HEADER=access_token   => gắn token vào header 'access_token'
     */
    public function sendMessage(string $accessToken, string $zaloUserId, string $textEn): array
    {
        $sendUrl = (string) env('ZL_SEND_URL', ''); // ví dụ: https://openapi.zalo.me/v3.0/oa/message/cs



        $authName = (string) env('ZL_SEND_AUTH_HEADER', 'access_token');

        if ($sendUrl === '') {
            Log::warning('[ZL][API] ZL_SEND_URL empty');
            return ['success' => false, 'error' => 'missing_send_url'];
        }

        // ===== Payload chuẩn OA =====
        $payload = [
            'recipient' => ['user_id' => $zaloUserId],
            'message'   => ['text'    => $textEn],
        ];

        try {
            // Chuẩn bị headers (tự prep Bearer nếu dùng Authorization)
            $headers = ['Content-Type' => 'application/json'];
            if (strtolower($authName) === 'authorization') {
                $headers['Authorization'] = (stripos($accessToken, 'Bearer ') === 0)
                    ? $accessToken
                    : ('Bearer ' . $accessToken);
            } else {
                $headers[$authName] = $accessToken; // ví dụ: 'access_token'
            }

            $resp = Http::timeout(20)
                ->withHeaders($headers)
                ->post($sendUrl, $payload);

            if (!$resp->ok()) {
                Log::warning('[ZL][API] send http error', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
                return [
                    'success' => false,
                    'status'  => $resp->status(),
                    'body'    => $resp->body(),
                ];
            }

            $j = $resp->json();

            /** ✅ V3: body mẫu
             * {"data":{"message_id":"...","user_id":"..."},"error":0,"message":"Success"}
             */
            $err = $j['error'] ?? null;
            $messageId = $j['data']['message_id']  // V3 chuẩn
                      ?? $j['message_id']          // fallback
                      ?? $j['msg_id']
                      ?? $j['id']
                      ?? null;

            /** Thành công nghiệp vụ khi error==0 và có message_id */
            if (($err === 0 || $err === '0') && $messageId) {
                Log::info('[ZL][API] send ok', [
                    'provider_message_id' => $messageId,
                    'raw'                 => $j,
                ]);
                return [
                    'success'             => true,
                    'provider_message_id' => $messageId,
                    'raw'                 => $j,
                ];
            }

            /** HTTP 200 nhưng nghiệp vụ fail */
            Log::warning('[ZL][API] send business error', ['body' => $j]);
            return [
                'success' => false,
                'body'    => $j,
            ];
        } catch (\Throwable $e) {
            Log::error('[ZL][API] send exception', ['err' => $e->getMessage()]);
            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }
}
