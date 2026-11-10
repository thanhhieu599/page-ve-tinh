<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Spatie\SchemaOrg\Article;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\FAQPage;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Person;
use Spatie\SchemaOrg\Product as ProductSchema;
use Spatie\SchemaOrg\Schema;

/**
 * Dữ liệu có cấu trúc (schema.org JSON-LD) sinh từ nội dung, cho Google và AI.
 * Dùng spatie/schema-org; không ghép JSON bằng tay.
 */
final class StructuredData
{
    /** D&T, thông tin thống nhất trên mọi site (config/company.php). */
    public static function organization(): Organization
    {
        $organization = Schema::organization()
            ->name((string) config('company.legal_name'))
            ->alternateName((string) config('company.name'))
            ->url((string) config('company.url'));

        // Mục chưa cấu hình thì bỏ hẳn, không khai giá trị rỗng.
        foreach (['logo' => 'logo', 'taxID' => 'tax_id', 'telephone' => 'phone', 'email' => 'email'] as $property => $key) {
            if (filled(config("company.{$key}"))) {
                $organization->setProperty($property, (string) config("company.{$key}"));
            }
        }

        if (filled(config('company.street_address'))) {
            $organization->address(Schema::postalAddress()
                ->streetAddress((string) config('company.street_address'))
                ->addressLocality((string) config('company.locality'))
                ->addressRegion((string) config('company.region'))
                ->addressCountry('VN'));
        }

        return $organization;
    }

    public static function article(Post $post, Site $site): Article
    {
        $article = Schema::article()
            ->headline($post->title)
            ->description((string) ($post->meta_description ?: $post->excerpt))
            ->mainEntityOfPage($site->url($post->path()))
            ->datePublished(self::local($post->published_at))
            ->dateModified(self::local($post->updated_at))
            ->publisher(self::organization());

        // Ảnh chia sẻ (OG) nếu có; không thì ảnh thư viện đầu tiên trong bài.
        $image = $post->og_image
            ? Storage::disk('public')->url($post->og_image)
            : ContentImages::firstLibraryImage($post->content, $post->site_id)?->url();
        if ($image !== null) {
            $article->image([$site->url($image)]);
        }

        if ($post->author) {
            $article->author(self::person($post->author->name, $site->url($post->author->path())));
        }

        return $article;
    }

    /**
     * Sản phẩm. Chỉ khai giá (offers) khi giá còn hạn 14 ngày, giống điều kiện hiện giá trên
     * trang, để dữ liệu có cấu trúc không bao giờ chứa giá cũ. Không khai tồn kho.
     */
    public static function product(Product $product, Site $site): ProductSchema
    {
        $url = $site->url($product->path());
        $schema = Schema::product()
            ->name($product->name)
            ->sku($product->sku)
            ->url($url);

        if (filled($product->description)) {
            $schema->description((string) $product->description);
        }

        if ($product->image) {
            $schema->image([$site->url($product->image->url())]);
        }

        if ($product->hasFreshPrice()) {
            $schema->offers(Schema::offer()
                ->price($product->price)
                ->priceCurrency('VND')
                ->priceValidUntil((string) $product->priceShownUntil()?->toDateString())
                ->url($url));
        }

        return $schema;
    }

    /**
     * Câu hỏi thường gặp của bài, đúng thứ tự và nội dung đang hiển thị (cùng nguồn $post->faqs).
     * Bài không có câu hỏi thì không có FAQPage.
     */
    public static function faqPage(Post $post): ?FAQPage
    {
        if ($post->faqs->isEmpty()) {
            return null;
        }

        return Schema::fAQPage()->mainEntity($post->faqs->map(
            fn (Faq $faq) => Schema::question()
                ->name($faq->question)
                ->acceptedAnswer(Schema::answer()->text($faq->answer)),
        )->all());
    }

    /**
     * Breadcrumb cho Google, từ đúng danh sách đang hiển thị trên trang.
     *
     * @param  list<array{0: string, 1: ?string}>  $trail  [nhãn, đường dẫn|null], mục cuối là trang hiện tại
     */
    public static function breadcrumbs(Site $site, array $trail, string $currentUrl): BreadcrumbList
    {
        $items = [];
        foreach (array_values($trail) as $index => [$label, $path]) {
            $items[] = Schema::listItem()
                ->position($index + 1)
                ->name($label)
                ->item($path === null ? $currentUrl : $site->url($path));
        }

        return Schema::breadcrumbList()->itemListElement($items);
    }

    private static function person(string $name, string $url): Person
    {
        return Schema::person()->name($name)->url($url);
    }

    /** Ngày giờ theo giờ Việt Nam (kèm múi giờ), như người đọc thấy trên trang. */
    private static function local(?CarbonInterface $at): ?CarbonInterface
    {
        return $at?->copy()->setTimezone((string) config('app.display_timezone'));
    }
}
