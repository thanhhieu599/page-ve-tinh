<?php

namespace App\Support;

use App\Models\CompanyProfile;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Spatie\SchemaOrg\Article;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\FAQPage;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Person;
use Spatie\SchemaOrg\Schema;

/**
 * Dữ liệu có cấu trúc (schema.org JSON-LD) sinh từ nội dung, cho Google và AI.
 * Dùng spatie/schema-org; không ghép JSON bằng tay.
 */
final class StructuredData
{
    /** D&T, thông tin thống nhất trên mọi site (CompanyProfile). */
    public static function organization(): Organization
    {
        $company = CompanyProfile::current();
        $organization = self::publisher()
            ->alternateName((string) $company->value('name'))
            ->url((string) $company->value('url'));

        $logo = (string) $company->value('logo_url');
        if (str_starts_with($logo, 'https://')) {
            $organization->logo($logo);
        }

        // Mục chưa cấu hình thì bỏ hẳn, không khai giá trị rỗng.
        foreach (['taxID' => 'tax_id', 'telephone' => 'phone', 'email' => 'email'] as $property => $key) {
            if (($value = $company->value($key)) !== null) {
                $organization->setProperty($property, $value);
            }
        }

        if ($company->value('street_address') !== null) {
            $organization->address(Schema::postalAddress()
                ->streetAddress((string) $company->value('street_address'))
                ->addressLocality((string) $company->value('locality'))
                ->addressRegion((string) $company->value('region'))
                ->addressCountry('VN'));
        }

        return $organization;
    }

    /**
     * Phần nhận diện của D&T: cùng @id với node Organization đầy đủ trên trang, để Google
     * hiểu publisher của bài chính là tổ chức đó.
     */
    private static function publisher(): Organization
    {
        return Schema::organization()
            ->identifier(rtrim((string) CompanyProfile::current()->value('url'), '/').'/#organization')
            ->name((string) CompanyProfile::current()->value('legal_name'));
    }

    public static function article(Post $post, Site $site): Article
    {
        $article = Schema::article()
            ->headline($post->title)
            ->mainEntityOfPage($site->url($post->path()))
            ->datePublished(self::local($post->published_at))
            ->dateModified(self::local($post->updated_at))
            ->publisher(self::publisher());

        $description = $post->meta_description ?: $post->excerpt;
        if (filled($description)) {
            $article->description((string) $description);
        }

        // Ảnh chia sẻ (OG) nếu có; không thì ảnh thư viện đầu tiên trong bài.
        $image = $post->og_image
            ? Storage::disk('public')->url($post->og_image)
            : ContentImages::firstLibraryImage($post->content, $post->site_id)?->url();
        if ($image !== null) {
            $article->image([$site->absoluteUrl($image)]);
        }

        if ($post->author) {
            $article->author(self::person($post->author->name, $site->url($post->author->path())));
        }

        return $article;
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
