<?php

namespace App\Support\SearchConsole;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gọi Google Search Console API (Search Analytics) bằng service account.
 *
 * File khoá JSON của service account nằm trên máy chủ, đường dẫn trong biến môi trường
 * GSC_CREDENTIALS_PATH; không lưu trong code hay database. Email service account phải được
 * thêm làm người dùng (quyền Đọc) ở từng property Search Console.
 */
final class SearchConsoleClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const API = 'https://searchconsole.googleapis.com/webmasters/v3/sites/';

    public static function configured(): bool
    {
        $path = (string) config('services.search_console.credentials');

        return $path !== '' && is_readable($path);
    }

    /**
     * Số liệu theo ngày và trang trong khoảng ngày (gồm cả hai đầu), tự lấy hết các trang kết quả.
     *
     * @return list<array{date: string, page: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function pageDaily(string $property, string $startDate, string $endDate): array
    {
        $rowLimit = (int) config('services.search_console.row_limit', 25000);
        $rows = [];

        for ($startRow = 0; ; $startRow += $rowLimit) {
            $batch = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(30)
                ->post(self::API.rawurlencode($property).'/searchAnalytics/query', [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'dimensions' => ['date', 'page'],
                    'rowLimit' => $rowLimit,
                    'startRow' => $startRow,
                ])
                ->throw()
                ->json('rows') ?? [];

            foreach ($batch as $row) {
                $rows[] = [
                    'date' => (string) $row['keys'][0],
                    'page' => (string) $row['keys'][1],
                    'clicks' => (int) $row['clicks'],
                    'impressions' => (int) $row['impressions'],
                    'ctr' => (float) $row['ctr'],
                    'position' => (float) $row['position'],
                ];
            }

            if (count($batch) < $rowLimit) {
                return $rows;
            }
        }
    }

    /** Access token OAuth từ JWT ký bằng khoá service account (giữ 50 phút). */
    private function accessToken(): string
    {
        return Cache::remember('search-console.access-token', now()->addMinutes(50), function (): string {
            $credentials = json_decode((string) file_get_contents((string) config('services.search_console.credentials')), true);
            if (! is_array($credentials) || ! isset($credentials['client_email'], $credentials['private_key'])) {
                throw new RuntimeException('File khoá service account Search Console không hợp lệ.');
            }
            $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

            $token = Http::asForm()->timeout(15)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => self::signedJwt($credentials['client_email'], $credentials['private_key'], $tokenUri),
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google không trả access token.');
            }

            return $token;
        });
    }

    private static function signedJwt(string $email, string $privateKey, string $audience): string
    {
        $now = time();
        $encode = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $email,
            'scope' => self::SCOPE,
            'aud' => $audience,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Không ký được JWT bằng khoá service account.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
