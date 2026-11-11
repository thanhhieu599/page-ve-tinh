<?php

namespace App\Support\PageCache;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

/**
 * Quy tắc cache toàn trang cho site vệ tinh (chỉ gắn vào route công khai, không có trang quản trị).
 *
 * - Không đọc, không ghi cache khi phiên của khách đang có lỗi form hoặc dữ liệu vừa nhập:
 *   trang đó chứa thông tin riêng của khách, không được lưu cho người khác.
 * - Không cache trang cảm ơn (ghi nhận chuyển đổi GA4 theo từng lượt gửi).
 * - Hết hạn lúc 0 giờ (giờ Việt Nam) để giá quá 14 ngày ẩn đúng ngày, và tối đa 1 giờ để bài
 *   hẹn giờ lên trang kịp lúc. Nội dung được lưu trong trang quản trị thì xoá cache ngay.
 */
class CacheSitePages extends CacheAllSuccessfulGetRequests
{
    public const MAX_LIFETIME = 3600;

    /** Route không bao giờ cache (trang cảm ơn; tìm kiếm: vô số từ khoá, tránh lấp đầy cache). */
    public const EXCLUDED_ROUTES = ['quote.thanks', 'search'];

    public function enabled(Request $request): bool
    {
        if (! parent::enabled($request)) {
            return false;
        }

        $session = $request->hasSession() ? $request->session() : null;

        return $session === null || (! $session->has('errors') && ! $session->has('_old_input'));
    }

    public function shouldCacheRequest(Request $request): bool
    {
        return parent::shouldCacheRequest($request) && ! $request->routeIs(...self::EXCLUDED_ROUTES);
    }

    public function cacheLifetimeInSeconds(Request $request): int
    {
        $now = CarbonImmutable::now((string) config('app.display_timezone'));
        $untilMidnight = (int) $now->diffInSeconds($now->addDay()->startOfDay());

        return max(1, min(self::MAX_LIFETIME, $untilMidnight));
    }
}
