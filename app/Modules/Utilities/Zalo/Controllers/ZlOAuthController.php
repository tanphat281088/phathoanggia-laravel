<?php

namespace App\Modules\Utilities\Zalo\Controllers;


use App\Modules\Utilities\Zalo\Services\ZaloOAuthService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OAuth v4 + PKCE cho Zalo (thực thi tối thiểu an toàn):
 * - /api/zl/oauth/redirect : tạo state + code_verifier, tính code_challenge, redirect URL
 * - /api/zl/oauth/callback : nhận code/state, đổi access_token + refresh_token, lưu mã hoá
 *
 * Lưu ý: DPoP sẽ bổ sung ở bước sau (gọi token/api kèm header DPoP).
 */
class ZlOAuthController extends Controller
{
    /**
     * GET /api/zl/oauth/redirect
     * Trả URL để FE redirect hoặc mở tab (giữ JSON để QA dễ kiểm).
     */
    public function redirect(Request $request)
    {
        $appId      = (string) env('ZL_APP_ID', '');
        $callback   = (string) env('ZL_CALLBACK_URL', '');
        $oauthBase  = rtrim((string) env('ZL_OAUTH_BASE', 'https://oauth.zaloapp.com'), '/');
        $permission = (string) env('ZL_PERMISSION_PATH', '/v4/permission');

        if ($appId === '' || $callback === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing ZL_APP_ID or ZL_CALLBACK_URL',
            ], 400);
        }

        // 1) Tạo state + code_verifier (43 ký tự A–Za–z0–9)
        $state        = Str::uuid()->toString();
        $codeVerifier = $this->randomCodeVerifier(43);

        // 2) Tính code_challenge = BASE64URL(SHA256(ASCII(code_verifier)))
        $codeChallenge = $this->codeChallengeFromVerifier($codeVerifier);

        // 3) Lưu state + code_verifier (mã hoá)
        try {
            DB::table('zl_oauth_states')->insert([
                'state'             => $state,
                'code_verifier_enc' => Crypt::encryptString($codeVerifier),
                'meta'              => json_encode(['initiator' => optional(auth()->user())->id]),
                'expires_at'        => now()->addMinutes(15),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ZL][OAuth] store state failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'store state failed'], 500);
        }

        // 4) Build URL v4/permission - đúng theo docs User Access Token V4
        // https://oauth.zaloapp.com/v4/permission?app_id=<APP_ID>&redirect_uri=<CALLBACK_URL>&code_challenge=<CODE_CHALLENGE>&state=<STATE>
        $url = $oauthBase . $permission . '?' . http_build_query([
            'app_id'        => $appId,
            'redirect_uri'  => $callback,
            'code_challenge'=> $codeChallenge,
            'state'         => $state,
        ]);



        return response()->json([
            'success' => true,
            'url'     => $url,
            'state'   => $state,
        ]);
    }

    /**
     * GET /api/zl/oauth/callback?code=...&state=...
     * Đổi access_token + refresh_token và lưu mã hoá vào zl_tokens.
     */
    public function callback(Request $request)
    {
        $code  = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return response()->json(['success' => false, 'message' => 'Missing code or state'], 400);
        }

        // 1) Lấy state
        $row = DB::table('zl_oauth_states')->where('state', $state)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Invalid state'], 400);
        }
        if (now()->greaterThan($row->expires_at)) {
            return response()->json(['success' => false, 'message' => 'State expired'], 400);
        }

        // 2) Giải mã code_verifier
        try {
            $codeVerifier = Crypt::decryptString($row->code_verifier_enc);
        } catch (\Throwable $e) {
            Log::error('[ZL][OAuth] decrypt code_verifier failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'decrypt verifier failed'], 500);
        }

        // 3) Gọi /v4/access_token (authorization_code)
        $accessBase = rtrim((string) env('ZL_OAUTH_BASE', 'https://oauth.zaloapp.com'), '/');
        $tokenPath  = (string) env('ZL_ACCESS_TOKEN_PATH', '/v4/access_token');
        $secretKey  = (string) env('ZL_SECRET_KEY', '');
        $appId      = (string) env('ZL_APP_ID', '');

        if ($secretKey === '' || $appId === '') {
            return response()->json(['success' => false, 'message' => 'Missing ZL_SECRET_KEY or ZL_APP_ID'], 400);
        }

        try {
            $resp = Http::timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'secret_key'   => $secretKey,
                    // 'DPoP'      => $this->makeDpopHeader("$accessBase$tokenPath", 'POST'), // sẽ bật ở bước DPoP
                ])
                ->asForm()
                ->post("$accessBase$tokenPath", [
                    'code'          => $code,
                    'app_id'        => $appId,
                    'grant_type'    => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (\Throwable $e) {
            Log::error('[ZL][OAuth] access_token request failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'token request error'], 500);
        }

        if (!$resp->ok()) {
            Log::warning('[ZL][OAuth] access_token http error', ['status' => $resp->status(), 'body' => $resp->body()]);
            return response()->json(['success' => false, 'message' => 'access_token http error'], 400);
        }

        $j = $resp->json();
        $accessToken  = (string) ($j['access_token']  ?? '');
        $refreshToken = (string) ($j['refresh_token'] ?? '');
        $expiresIn    = (int)    ($j['expires_in']     ?? 3600);
        // Zalo có trường refresh_token_expires_in (chuỗi) → fallback 30 ngày = 2592000s nếu thiếu
        $refreshTtl   = (int)    ($j['refresh_token_expires_in'] ?? 2592000);

        if ($accessToken === '' || $refreshToken === '') {
            Log::warning('[ZL][OAuth] missing token fields', ['json' => $j]);
            return response()->json(['success' => false, 'message' => 'invalid token payload'], 400);
        }

        // 4) Lưu token (mã hoá)
        try {
            $svc = new ZaloOAuthService();
            $svc->storeEncrypted($accessToken, $refreshToken, $expiresIn, $refreshTtl, null);
            // dọn state sau khi dùng xong
            DB::table('zl_oauth_states')->where('state', $state)->delete();
        } catch (\Throwable $e) {
            Log::error('[ZL][OAuth] store token failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'store token failed'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token stored (encrypted). Next: enable DPoP + refresh worker.',
            'ttl'     => ['access' => $expiresIn, 'refresh' => $refreshTtl],
        ]);
    }

    /* ==================== Helpers ==================== */

    /** Tạo code_verifier 43 ký tự [A-Za-z0-9] */
    private function randomCodeVerifier(int $len = 43): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    /** code_challenge = BASE64URL(SHA256(ASCII(code_verifier))) */
    private function codeChallengeFromVerifier(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    // private function makeDpopHeader(string $url, string $method): string
    // {
    //     // Sẽ triển khai ở bước DPoP: tạo JWT 'dpop+jwt' với ES256 từ private key.
    //     return '';
    // }
}
