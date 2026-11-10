<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Database\Seeder;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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

    public const CATEGORY_SLUG = 'giay-van-phong';

    public const CATEGORY_NAME = 'Giấy văn phòng';

    public const AUTHOR_SLUG = 'bien-tap-dt';

    public const AUTHOR_NAME = 'Ban biên tập D&T';

    public const FAQ_QUESTION = 'Nên chọn giấy 70gsm hay 80gsm?';

    public const IMAGE_ALT = 'Ram giấy A4 xếp trên kệ văn phòng';

    public function run(): void
    {
        $site = Site::query()->updateOrCreate(
            ['domain' => self::DOMAIN],
            ['name' => 'Văn phòng phẩm D&T', 'theme' => 'vpp', 'settings' => [], 'is_active' => true],
        );

        $category = Category::query()->firstOrNew(['site_id' => $site->id, 'slug' => self::CATEGORY_SLUG]);
        $category->fill(['name' => self::CATEGORY_NAME, 'description' => 'Giấy in, giấy photo cho văn phòng nhà máy.'])->save();

        $author = Author::query()->updateOrCreate(['slug' => self::AUTHOR_SLUG], [
            'name' => self::AUTHOR_NAME,
            'job_title' => 'Biên tập nội dung',
            'bio' => 'Nhóm biên tập nội dung kỹ thuật của D&T.',
        ]);

        $post = Post::query()->firstOrNew(['site_id' => $site->id, 'slug' => self::POST_SLUG]);
        $post->author_id = $author->id;
        $image = $this->demoImage($site->id);
        $post->fill([
            'title' => self::POST_TITLE,
            'excerpt' => 'Hướng dẫn chọn giấy in A4 theo định lượng, độ trắng và khối lượng in.',
            'content' => '<p>'.self::POST_MARKER.'</p><p><img src="'.$image->url().'" alt=""></p>',
        ]);
        $post->publish(now()->subDay());
        $post->categories()->sync([$category->id]);
        $post->faqs()->delete();
        $post->faqs()->create(['question' => self::FAQ_QUESTION, 'answer' => 'In nội bộ dùng 70gsm; hồ sơ gửi khách dùng 80gsm.']);
    }

    /** Một ảnh mẫu trong thư viện (tạo bằng GD, không cần file có sẵn). */
    private function demoImage(int $siteId): Media
    {
        $existing = Media::query()->where('site_id', $siteId)->where('alt', self::IMAGE_ALT)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        (new ImageManager(new Driver))->create(1600, 900)->fill('#e8eef5')->save($path);

        try {
            return MediaLibrary::store($path, self::IMAGE_ALT, $siteId);
        } finally {
            @unlink($path);
        }
    }
}
