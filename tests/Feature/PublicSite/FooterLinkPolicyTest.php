<?php

namespace Tests\Feature\PublicSite;

use App\Enums\KeywordIntent;
use App\Models\KeywordMap;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Footer lặp trên mọi trang của site: không được thành nơi nhồi anchor từ khoá
 * hay liên kết chéo giữa các site vệ tinh. Kiểm tra cho từng theme đang có.
 */
class FooterLinkPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** Nhãn điều hướng được phép trong footer (không phải từ khoá SEO). */
    private const NAVIGATION_LABELS = ['Yêu cầu báo giá', 'Giới thiệu', 'Chính sách quyền riêng tư', 'Liên hệ'];

    /** @return array<string, array{string}> */
    public static function themes(): array
    {
        $themes = [];
        // Data provider chạy trước khi ứng dụng khởi động: dùng đường dẫn theo file.
        foreach (glob(dirname(__DIR__, 3).'/resources/themes/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $themes[basename($directory)] = [basename($directory)];
        }

        return $themes;
    }

    #[DataProvider('themes')]
    public function test_footer_states_ownership_and_carries_no_keyword_anchors_or_network_links(string $theme): void
    {
        config(['company.legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T']);
        $site = Site::factory()->create(['domain' => 'site-kiem-tra.vn', 'name' => 'Site kiểm tra', 'theme' => $theme]);
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
        foreach (['văn phòng phẩm', 'giấy in a4', 'yêu cầu báo giá văn phòng phẩm'] as $keyword) {
            KeywordMap::withoutGlobalScopes()->forceCreate([
                'site_id' => $site->id, 'keyword' => $keyword, 'target_url' => '/', 'intent' => KeywordIntent::Commercial,
            ]);
        }

        $html = (string) $this->get('https://site-kiem-tra.vn/yeu-cau-bao-gia')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $footer), "Theme {$theme} phải có footer.");
        $footer = $footer[0];
        $this->assertStringContainsString('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', $footer, 'Footer phải ghi rõ thuộc D&T.');

        preg_match_all('#<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>#s', $footer, $links, PREG_SET_ORDER);
        $allowed = [...self::NAVIGATION_LABELS, 'Site kiểm tra'];
        foreach ($links as [, $href, $text]) {
            $label = trim(html_entity_decode(strip_tags($text)));
            $this->assertContains($label, $allowed, "Footer theme {$theme} có anchor không phải nhãn điều hướng: \"{$label}\".");
            $this->assertNull(KeywordMap::ownerOf($label), "Footer theme {$theme} có anchor trùng từ khoá: \"{$label}\".");
            $this->assertStringNotContainsString('ppetrangthietbi.vn', $href, "Footer theme {$theme} link sang site vệ tinh khác.");
            $this->assertStringNotContainsString('trangthietbi.vn', (string) parse_url($href, PHP_URL_HOST) ?: '', "Footer theme {$theme} không được link sang trangthietbi.vn trên mọi trang.");
        }
    }
}
