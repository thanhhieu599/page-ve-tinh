<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Kiểm tra chống bot Cloudflare Turnstile ở phía server.
 * Chưa cấu hình khoá thì coi như tắt (môi trường local).
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function enabled(): bool
    {
        return filled(config('sites.turnstile.secret_key')) && filled(config('sites.turnstile.site_key'));
    }

    /** true: token hợp lệ. Lỗi mạng tới Cloudflare cũng tính là không hợp lệ. */
    public static function verify(string $token, ?string $ip): bool
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('sites.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $response->ok() && $response->json('success') === true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
