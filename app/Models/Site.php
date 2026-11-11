<?php

namespace App\Models;

use App\Support\Indexing\IndexReadiness;
use Database\Factories\SiteFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Một domain vệ tinh. Mỗi ngành hàng là một site.
 */
class Site extends Model
{
    // Định dạng hợp lệ của các khoá cấu hình (dùng chung cho form quản trị và khi đọc).
    public const GA4_PATTERN = '/^G-[A-Z0-9]{4,20}$/';

    public const GSC_TOKEN_PATTERN = '/^[A-Za-z0-9_-]{10,100}$/';

    public const COLOR_PATTERN = '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    public const INDUSTRY_TAG_PATTERN = '/^[a-z0-9-]{1,50}$/';

    /** Property Search Console: sc-domain:ten-mien hoặc https://.../ (có "/" cuối). */
    public const GSC_PROPERTY_PATTERN = '#^(sc-domain:[a-z0-9.-]+|https://[a-z0-9.-]+/)$#';

    public const LOGO_DIRECTORY = 'site-logos';

    public const BANNER_DIRECTORY = 'site-banners';

    public const MAX_BANNERS = 5;

    /** Chiều cao hiển thị logo ở header (px). */
    public const LOGO_HEIGHT = 48;

    /** Chỉ thị robots cho site chưa bật index. */
    public const NOINDEX = 'noindex, nofollow';

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = ['domain', 'name', 'theme', 'settings', 'is_active', 'requires_expert_review'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'is_indexable' => 'boolean',
            'requires_expert_review' => 'boolean',
        ];
    }

    /** Mã GA4 trong cấu hình site, chỉ nhận đúng dạng G-XXXX để không chèn được mã lạ vào trang. */
    public function ga4Id(): ?string
    {
        $id = $this->settings['ga4_id'] ?? null;

        return is_string($id) && preg_match(self::GA4_PATTERN, $id) ? $id : null;
    }

    /** Logo của site (đường dẫn công khai trên disk public), nếu đã tải lên. */
    public function logoUrl(): ?string
    {
        $path = $this->settings['logo'] ?? null;

        return is_string($path) && str_starts_with($path, self::LOGO_DIRECTORY.'/') ? Storage::disk('public')->url($path) : null;
    }

    /** Chiều rộng logo khi hiển thị cao LOGO_HEIGHT, giữ tỉ lệ ảnh (tránh xô lệch trang). */
    public function logoWidth(): ?int
    {
        $width = $this->settings['logo_width'] ?? null;
        $height = $this->settings['logo_height'] ?? null;

        return is_int($width) && is_int($height) && $height > 0 ? (int) round($width * self::LOGO_HEIGHT / $height) : null;
    }

    /** Màu thương hiệu dạng #rgb hoặc #rrggbb; chỉ nhận đúng dạng để không chèn được CSS lạ. */
    public function brandColor(): ?string
    {
        $color = $this->settings['primary_color'] ?? null;

        return is_string($color) && preg_match(self::COLOR_PATTERN, $color) ? strtolower($color) : null;
    }

    /**
     * Banner trang chủ đã tải lên (theo thứ tự), chỉ nhận ảnh trong thư mục banner và link trong site.
     *
     * @return list<array{url: string, alt: string, href: ?string, width: ?int, height: ?int}>
     */
    public function banners(): array
    {
        $banners = [];
        foreach (array_values((array) ($this->settings['banners'] ?? [])) as $banner) {
            $image = is_array($banner) ? ($banner['image'] ?? null) : null;
            if (! is_string($image) || ! str_starts_with($image, self::BANNER_DIRECTORY.'/')) {
                continue;
            }
            $href = is_string($banner['url'] ?? null) && preg_match('#^/[^/]#', $banner['url']) ? $banner['url'] : null;
            $banners[] = [
                'url' => Storage::disk('public')->url($image),
                'alt' => is_string($banner['alt'] ?? null) ? $banner['alt'] : $this->name,
                'href' => $href,
                'width' => is_int($banner['width'] ?? null) ? $banner['width'] : null,
                'height' => is_int($banner['height'] ?? null) ? $banner['height'] : null,
            ];
        }

        return array_slice($banners, 0, self::MAX_BANNERS);
    }

    /** Câu giới thiệu trang chủ (cũng là meta description trang chủ); trống thì theme dùng câu mặc định. */
    public function tagline(): ?string
    {
        $tagline = $this->settings['tagline'] ?? null;

        return is_string($tagline) && trim($tagline) !== '' ? trim($tagline) : null;
    }

    /** Mã xác minh Google Search Console (thẻ meta google-site-verification). */
    public function searchConsoleToken(): ?string
    {
        $token = $this->settings['gsc_verification'] ?? null;

        return is_string($token) && preg_match(self::GSC_TOKEN_PATTERN, $token) ? $token : null;
    }

    /** Property Search Console để kéo số liệu; chưa cấu hình thì không kéo. */
    public function searchConsoleProperty(): ?string
    {
        $property = $this->settings['gsc_property'] ?? null;

        return is_string($property) && preg_match(self::GSC_PROPERTY_PATTERN, $property) ? $property : null;
    }

    /** Campaign Mautic nhận lead của site (settings.mautic_campaign_id). */
    public function mauticCampaignId(): ?int
    {
        $id = $this->settings['mautic_campaign_id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /** Mã ngành gắn tag Mautic `nganh-<mã>` (settings.industry_tag), chỉ chữ thường, số, gạch nối. */
    public function industryTag(): ?string
    {
        $tag = $this->settings['industry_tag'] ?? null;

        return is_string($tag) && preg_match(self::INDUSTRY_TAG_PATTERN, $tag) ? $tag : null;
    }

    /**
     * URL tuyệt đối trên domain của site (https khi FORCE_HTTPS bật, như production).
     * Dùng cho canonical, sitemap, robots.txt.
     */
    public function url(string $path): string
    {
        return (config('sites.force_https') ? 'https' : 'http').'://'.$this->domain.$path;
    }

    /**
     * Địa chỉ tuyệt đối cho một URL công khai: giữ nguyên nếu đã tuyệt đối (vd. ảnh trên CDN),
     * còn đường dẫn tương đối (/storage/...) thì ghép domain của site.
     */
    public function absoluteUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) === 1 ? $url : $this->url($url);
    }

    /** Cho Google index site. Chỉ được khi site đạt checklist (IndexReadiness). */
    public function enableIndexing(): void
    {
        if (! IndexReadiness::for($this)->isReady()) {
            throw new DomainException('Site chưa đạt đủ điều kiện mở index.');
        }

        $this->forceFill(['is_indexable' => true])->save();
    }

    public function disableIndexing(): void
    {
        $this->forceFill(['is_indexable' => false])->save();
    }

    public static function findActiveByHost(string $host): ?self
    {
        return static::query()
            ->where('domain', strtolower($host))
            ->where('is_active', true)
            ->first();
    }

    protected static function booted(): void
    {
        // Logo mới: ghi kích thước thật để <img> có width/height; xoá file logo cũ.
        static::saving(function (Site $site): void {
            $settings = $site->settings ?? [];
            $logo = $settings['logo'] ?? null;
            $previous = $site->getOriginal('settings')['logo'] ?? null;

            // Tính lại khi đổi logo, hoặc khi form quản trị lưu lại settings không kèm kích thước.
            // Banner: ghi kích thước thật cho từng ảnh (width/height trên <img>, tránh xô lệch trang).
            if (isset($settings['banners']) && is_array($settings['banners'])) {
                $settings['banners'] = array_values(array_map(function ($banner) {
                    $image = is_array($banner) ? ($banner['image'] ?? null) : null;
                    $disk = Storage::disk('public');
                    if (is_string($image) && $disk->exists($image) && ($size = @getimagesize($disk->path($image))) !== false) {
                        [$banner['width'], $banner['height']] = [$size[0], $size[1]];
                    }

                    return $banner;
                }, $settings['banners']));
                $site->settings = $settings;
            }

            if ($logo !== $previous || ! isset($settings['logo_width'], $settings['logo_height'])) {
                unset($settings['logo_width'], $settings['logo_height']);
                $disk = Storage::disk('public');
                if (is_string($logo) && $disk->exists($logo) && ($size = @getimagesize($disk->path($logo))) !== false) {
                    [$settings['logo_width'], $settings['logo_height']] = [$size[0], $size[1]];
                }
                $site->settings = $settings;
            }
        });

        static::saved(function (Site $site): void {
            if (! $site->wasChanged('settings')) {
                return;
            }
            $before = $site->getOriginal('settings') ?? [];
            $after = $site->settings ?? [];

            // Xoá file logo/banner không còn dùng.
            $images = fn (array $settings) => array_filter([
                $settings['logo'] ?? null,
                ...array_map(fn ($banner) => is_array($banner) ? ($banner['image'] ?? null) : null, (array) ($settings['banners'] ?? [])),
            ], 'is_string');
            foreach (array_diff($images($before), $images($after)) as $unused) {
                Storage::disk('public')->delete($unused);
            }
        });

        // Site mới luôn có đủ trang bắt buộc (nháp, nội dung mẫu) để biên tập hoàn thiện.
        static::created(fn (Site $site) => RequiredPage::ensureRequiredFor($site));
    }

    /** @return HasMany<RequiredPage, $this> */
    public function requiredPages(): HasMany
    {
        return $this->hasMany(RequiredPage::class);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
