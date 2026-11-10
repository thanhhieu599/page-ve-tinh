<?php

namespace App\Support\SearchConsole;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gọi Google Search Console API (Search Analytics) bằng service account.
 *
 * File khoá JSON của service account nằm trên máy chủ, đường dẫn trong biến môi trường
 * GSC_CREDENTIALS_PATH; không lưu trong code hay database. Email service account phải được
 * thêm làm người dùng (quyền Đọc) ở từng property Search Console. Hướng dẫn: docs/search-console.md.
 */
final class SearchConsoleClient
{
    /** Search Console tính ngày theo giờ Thái Bình Dương (Mỹ). */
    public const TIMEZONE = 'America/Los_Angeles';

    /** Số liệu trễ khoảng 2 ngày. */
    public const DATA_DELAY_DAYS = 2;

    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const API = 'https://searchconsole.googleapis.com/webmasters/v3/sites/';

    public static function configured(): bool
    {
        $path = (string) config('services.search_console.credentials');

        return $path !== '' && is_readable($path);
    }

    /** Ngày gần nhất thường đã có số liệu (theo lịch của Search Console). */
    public static function latestDataDay(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay()->subDays(self::DATA_DELAY_DAYS);
    }

    /**
     * Số liệu theo ngày và trang (gồm cả hai đầu khoảng ngày), lấy hết các trang kết quả.
     *
     * @return list<array{date: string, page: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function pageDaily(string $property, CarbonInterface $start, CarbonInterface $end): array
    {
        return array_map(fn (array $row): array => [
            'date' => (string) $row['keys'][0],
            'page' => (string) $row['keys'][1],
            ...self::metrics($row),
        ], $this->query($property, $start, $end, ['date', 'page']));
    }

    /**
     * Tổng của cả property theo ngày (như màn hình Search Console).
     *
     * @return list<array{date: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function siteDaily(string $property, CarbonInterface $start, CarbonInterface $end): array
    {
        return array_map(fn (array $row): array => [
            'date' => (string) $row['keys'][0],
            ...self::metrics($row),
        ], $this->query($property, $start, $end, ['date']));
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function query(string $property, CarbonInterface $start, CarbonInterface $end, array $dimensions): array
    {
        $rowLimit = (int) config('services.search_console.row_limit', 25000);
        $rows = [];

        for ($startRow = 0; ; $startRow += $rowLimit) {
            $batch = $this->post(self::API.rawurlencode($property).'/searchAnalytics/query', [
                'startDate' => $start->toDateString(),
                'endDate' => $end->toDateString(),
                'dimensions' => $dimensions,
                // Gồm cả số liệu tạm của ngày gần nhất; lần chạy sau ghi đè bằng số chốt.
                'dataState' => 'all',
                'rowLimit' => $rowLimit,
                'startRow' => $startRow,
            ]);

            array_push($rows, ...$batch);

            if (count($batch) < $rowLimit) {
                return $rows;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function post(string $url, array $body): array
    {
        $send = fn () => Http::withToken($this->accessToken())->acceptJson()->timeout(20)->post($url, $body);
        $response = $send();

        // Token bị thu hồi/hết hạn trước thời gian giữ: đăng nhập lại một lần.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $send();
        }

        return $response->throw()->json('rows') ?? [];
    }

    /** @param  array<string, mixed>  $row */
    private static function metrics(array $row): array
    {
        return [
            'clicks' => (int) $row['clicks'],
            'impressions' => (int) $row['impressions'],
            'ctr' => (float) $row['ctr'],
            'position' => (float) $row['position'],
        ];
    }

    /** Access token OAuth từ JWT ký bằng khoá service account (giữ 50 phút). */
    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
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

    /** Khoá bộ nhớ đệm theo service account: đổi khoá/tài khoản thì không dùng nhầm token cũ. */
    private function tokenCacheKey(): string
    {
        return 'search-console.access-token.'.sha1((string) $this->credentials()['client_email']);
    }

    /** @return array{client_email: string, private_key: string, token_uri?: string} */
    private function credentials(): array
    {
        $credentials = json_decode((string) @file_get_contents((string) config('services.search_console.credentials')), true);

        if (! is_array($credentials) || ! is_string($credentials['client_email'] ?? null) || ! is_string($credentials['private_key'] ?? null)) {
            throw new RuntimeException('File khoá service account Search Console không đọc được hoặc không hợp lệ.');
        }

        return $credentials;
    }

    private static function signedJwt(string $email, string $privateKey, string $audience): string
    {
        $now = time();
        $unsigned = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'
            .self::base64Url((string) json_encode(['iss' => $email, 'scope' => self::SCOPE, 'aud' => $audience, 'iat' => $now, 'exp' => $now + 3600]));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Không ký được JWT bằng khoá service account.');
        }

        return $unsigned.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
