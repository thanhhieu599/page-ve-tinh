<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu cho môi trường local và kiểm tra trình duyệt trong CI.
 * Không chạy trên production.
 */
class DemoSiteSeeder extends Seeder
{
    public const DOMAIN = 'vpptrangthietbi.com.vn';

    public const POST_SLUG = 'cach-chon-giay-in-a4';

    public const POST_TITLE = 'Cách chọn giấy in A4 cho văn phòng nhà máy';

    public const POST_MARKER = 'Định lượng 70gsm phù hợp in nội bộ, 80gsm cho hồ sơ gửi khách hàng.';

    public function run(): void
    {
        $site = Site::query()->updateOrCreate(
            ['domain' => self::DOMAIN],
            ['name' => 'Văn phòng phẩm D&T', 'theme' => 'vpp', 'settings' => [], 'is_active' => true],
        );

        Post::query()->updateOrCreate(
            ['site_id' => $site->id, 'slug' => self::POST_SLUG],
            [
                'title' => self::POST_TITLE,
                'excerpt' => 'Hướng dẫn chọn giấy in A4 theo định lượng, độ trắng và khối lượng in.',
                'content' => '<p>'.self::POST_MARKER.'</p>',
                'status' => PostStatus::Published,
                'published_at' => now()->subDay(),
            ],
        );
    }
}
