<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Product;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
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

    public const PRODUCT_SLUG = 'giay-double-a-a4-80gsm';

    public const PRODUCT_NAME = 'Giấy Double A A4 80gsm';

    public function run(): void
    {
        $site = Site::query()->updateOrCreate(
            ['domain' => self::DOMAIN],
            ['name' => 'Văn phòng phẩm D&T', 'theme' => 'vpp', 'settings' => ['tagline' => 'Văn phòng phẩm cho nhà máy và văn phòng doanh nghiệp: báo giá theo số lượng, xuất VAT, giao tận KCN.'], 'is_active' => true],
        );

        // Trang bắt buộc: site tạo từ trước có thể chưa có; bật hiển thị để xem thử khi chạy local.
        RequiredPage::ensureRequiredFor($site);
        $site->requiredPages()->withoutGlobalScopes()->update(['is_published' => true]);

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

        $product = Product::query()->firstOrNew(['site_id' => $site->id, 'sku' => 'DA-A4-80']);
        $product->fill([
            'name' => self::PRODUCT_NAME,
            'slug' => self::PRODUCT_SLUG,
            'specs' => ['Định lượng' => '80gsm', 'Quy cách' => '500 tờ/ram, 5 ram/thùng'],
            'buy_url' => 'https://trangthietbi.vn/giay-double-a-a4-80gsm',
            'media_id' => $image->id,
            'description' => 'Giấy in A4 định lượng 80gsm cho hồ sơ gửi khách hàng.',
        ]);
        $product->site_id = $site->id;
        $product->save();
        $product->setPrice(89000);
        $post->products()->sync([$product->id]);
        $post->faqs()->delete();
        $post->faqs()->create(['question' => self::FAQ_QUESTION, 'answer' => 'In nội bộ dùng 70gsm; hồ sơ gửi khách dùng 80gsm.']);

        $product->forceFill(['category_id' => $category->id, 'is_featured' => true])->save();
        $this->seedCatalog($site, $category);
    }

    /**
     * Danh mục và sản phẩm mẫu để xem giao diện cửa hàng (chạy lại không tạo trùng).
     * [danh mục cha, danh mục con|null, SKU, tên, giá, nổi bật, màu ảnh]
     */
    private function seedCatalog(Site $site, Category $paper): void
    {
        $catalog = [
            ['Giấy văn phòng', null, 'PO-A4-70', 'Giấy Paper One A4 70gsm – Ram 500 tờ', 75000, false, '#1e88e5'],
            ['Giấy văn phòng', null, 'IK-A4-70', 'Giấy IK Plus A4 70gsm – Ram 500 tờ', 72000, false, '#43a047'],
            ['Giấy văn phòng', null, 'DA-A3-80', 'Giấy Double A A3 80gsm – Ram 500 tờ', 178000, false, '#8e24aa'],
            ['Tập vở – Sổ tay', null, 'SO-A5-DA', 'Sổ bìa da A5 Deli 200 trang', 45000, false, '#6d4c41'],
            ['Tập vở – Sổ tay', null, 'TAP-200', 'Tập kẻ ngang 200 trang – Lốc 10 cuốn', 180000, false, '#00897b'],
            ['Dụng cụ văn phòng', 'Bấm kim và đục lỗ', 'E0130', 'Máy đục lỗ Deli E0130 – 100 tờ', 865700, true, '#9e9e9e'],
            ['Dụng cụ văn phòng', 'Bấm kim và đục lỗ', 'E0395', 'Máy bấm kim Deli E0395 – 210 tờ', 546600, true, '#424242'],
            ['Dụng cụ văn phòng', 'Bấm kim và đục lỗ', 'E0117', 'Đục lỗ Deli E0117 – 35 tờ', 198800, false, '#c62828'],
            ['Dụng cụ văn phòng', 'Kẹp tài liệu', 'E8551A', 'Kẹp bướm màu 51mm – Hộp 12 cái', 52900, false, '#f9a825'],
            ['Dụng cụ văn phòng', 'Kẹp tài liệu', 'E8553A', 'Kẹp bướm màu 32mm – Hộp 24 cái', 44600, false, '#ef6c00'],
            ['Dụng cụ văn phòng', null, 'E6032', 'Kéo văn phòng Deli E6032', 20300, false, '#d81b60'],
            ['File và lưu trữ', null, 'BC-7CM', 'Bìa còng 7cm khổ A4', 42000, false, '#3949ab'],
            ['File và lưu trữ', null, 'FH-10CM', 'File hộp 10cm có khoá', 38000, false, '#0277bd'],
            ['Dụng cụ viết và vẽ', null, 'TL-027', 'Bút bi Thiên Long TL-027 – Hộp 20 cây', 76000, true, '#1565c0'],
            ['Dụng cụ viết và vẽ', null, 'HL-03', 'Bút dạ quang màu vàng', 9500, false, '#fdd835'],
            ['Máy tính', null, 'W1710', 'Máy tính kỹ thuật Deli W1710', 140100, true, '#37474f'],
        ];

        $categories = [$paper->name => $paper];
        $category = function (string $name, ?Category $parent) use ($site, &$categories): Category {
            $key = ($parent?->name ?? '').'/'.$name;
            if (! isset($categories[$key]) && ! ($parent === null && isset($categories[$name]))) {
                $model = Category::query()->firstOrNew(['site_id' => $site->id, 'slug' => Str::slug($name)]);
                $model->fill(['name' => $name, 'parent_id' => $parent?->id])->save();
                $categories[$key] = $model;
            }

            return $categories[$key] ?? $categories[$name];
        };

        foreach ($catalog as [$rootName, $childName, $sku, $name, $price, $featured, $color]) {
            $root = $category($rootName, null);
            $target = $childName !== null ? $category($childName, $root) : $root;

            $product = Product::query()->firstOrNew(['site_id' => $site->id, 'sku' => $sku]);
            $product->fill(['name' => $name, 'slug' => Str::slug($name), 'is_active' => true, 'media_id' => $this->productImage($site->id, $name, $color)->id]);
            $product->site_id = $site->id;
            $product->forceFill(['category_id' => $target->id, 'is_featured' => $featured])->save();
            $product->setPrice($price);
        }
    }

    /** Ảnh sản phẩm mẫu: nền trắng, khối màu đại diện sản phẩm. */
    private function productImage(int $siteId, string $alt, string $color): Media
    {
        $existing = Media::query()->where('site_id', $siteId)->where('alt', $alt)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        (new ImageManager(new Driver))->create(800, 800)->fill('#ffffff')
            ->drawRectangle(200, 260, fn ($rectangle) => $rectangle->size(400, 280)->background($color))
            ->drawRectangle(200, 560, fn ($rectangle) => $rectangle->size(400, 30)->background('#c1272d'))
            ->save($path);

        try {
            return MediaLibrary::store($path, $alt, $siteId);
        } finally {
            @unlink($path);
        }
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
