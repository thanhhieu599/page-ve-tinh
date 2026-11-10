<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PageKind;
use App\Models\CompanyProfile;
use App\Models\RequiredPage;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredPagesTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        CompanyProfile::stored()->fill([
            'name' => 'D&T',
            'legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T',
            'url' => 'https://trangthietbi.vn',
            'tax_id' => '0312345678',
            'phone' => '028 1234 5678',
            'email' => 'sales@trangthietbi.vn',
            'street_address' => '12 Đường Số 7',
            'locality' => 'Quận Tân Phú',
            'region' => 'TP. Hồ Chí Minh',
        ])->save();
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);
    }

    private function page(Site $site, PageKind $kind): RequiredPage
    {
        return RequiredPage::withoutGlobalScopes()->where('site_id', $site->id)->where('kind', $kind)->firstOrFail();
    }

    private function publish(Site $site, PageKind $kind): RequiredPage
    {
        $page = $this->page($site, $kind);
        $page->update(['is_published' => true]);

        return $page;
    }

    public function test_a_new_site_starts_with_the_three_required_pages_as_unpublished_drafts(): void
    {
        foreach (PageKind::cases() as $kind) {
            $page = $this->page($this->vpp, $kind);
            $this->assertFalse($page->is_published);
            $this->assertNotSame('', trim(strip_tags($page->content)), "Trang {$kind->value} phải có nội dung mẫu.");
            $this->get('https://vpptrangthietbi.com.vn'.$kind->path())->assertNotFound();
        }

        $this->assertStringContainsString('Văn phòng phẩm D&amp;T', $this->page($this->vpp, PageKind::About)->content);
        $this->assertStringContainsString('dữ liệu cá nhân', $this->page($this->vpp, PageKind::Privacy)->content);
    }

    public function test_published_about_and_contact_pages_show_the_same_company_details_on_every_site(): void
    {
        foreach ([$this->vpp, $this->ppe] as $site) {
            $this->publish($site, PageKind::About);
            $this->publish($site, PageKind::Contact);

            foreach ([PageKind::About, PageKind::Contact] as $kind) {
                $this->get("https://{$site->domain}".$kind->path())
                    ->assertOk()
                    ->assertSee('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', escape: false)
                    ->assertSee('Mã số thuế: 0312345678')
                    ->assertSee('12 Đường Số 7, Quận Tân Phú, TP. Hồ Chí Minh')
                    ->assertSee('028 1234 5678')
                    ->assertSee('sales@trangthietbi.vn');
            }
        }
    }

    public function test_the_contact_page_carries_the_quote_request_form(): void
    {
        $this->publish($this->vpp, PageKind::Contact);

        $this->get('https://vpptrangthietbi.com.vn/lien-he')
            ->assertOk()
            ->assertSee('class="quote-form"', escape: false)
            ->assertSee('action="/yeu-cau-bao-gia"', escape: false);
    }

    public function test_footer_and_sitemap_list_only_published_required_pages(): void
    {
        $this->publish($this->vpp, PageKind::Privacy);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('<a href="/chinh-sach-quyen-rieng-tu">Chính sách quyền riêng tư</a>', escape: false)
            ->assertDontSee('href="/gioi-thieu"', escape: false)
            ->assertDontSee('href="/lien-he"', escape: false);

        $sitemap = (string) $this->get('https://vpptrangthietbi.com.vn/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>https://vpptrangthietbi.com.vn/chinh-sach-quyen-rieng-tu</loc>', $sitemap);
        $this->assertStringNotContainsString('/gioi-thieu', $sitemap);
    }

    public function test_the_consent_checkbox_links_to_the_privacy_policy_page(): void
    {
        $this->publish($this->vpp, PageKind::Privacy);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->getContent();
        $this->assertSame(1, preg_match('#<form[^>]*class="quote-form".*?</form>#s', $html, $form));
        $this->assertStringContainsString('href="/chinh-sach-quyen-rieng-tu"', $form[0]);
        $this->get('https://vpptrangthietbi.com.vn/chinh-sach-quyen-rieng-tu')->assertOk();
    }

    public function test_sites_created_before_this_feature_get_their_missing_pages_from_the_command(): void
    {
        RequiredPage::withoutGlobalScopes()->where('site_id', $this->vpp->id)->where('kind', PageKind::Contact)->delete();
        $about = $this->publish($this->vpp, PageKind::About);

        $this->artisan('sites:ensure-pages')->assertSuccessful();

        $this->assertFalse($this->page($this->vpp, PageKind::Contact)->is_published);
        $this->assertTrue($about->fresh()->is_published, 'Trang đã có không bị ghi đè.');
        $this->assertSame(6, RequiredPage::withoutGlobalScopes()->count());
    }

    public function test_the_footer_of_every_page_shows_the_full_company_details(): void
    {
        foreach (['vpptrangthietbi.com.vn', 'ppetrangthietbi.vn'] as $domain) {
            $html = (string) $this->get("https://{$domain}/yeu-cau-bao-gia")->getContent();
            $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $footer));
            foreach (['Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', '0312345678', '12 Đường Số 7, Quận Tân Phú, TP. Hồ Chí Minh', '028 1234 5678', 'sales@trangthietbi.vn'] as $detail) {
                $this->assertStringContainsString($detail, $footer[0], "Footer {$domain} thiếu: {$detail}");
            }
        }
    }

    public function test_pages_always_show_the_current_company_details_even_after_they_change(): void
    {
        $this->publish($this->vpp, PageKind::About);
        $this->publish($this->vpp, PageKind::Privacy);

        CompanyProfile::stored()->fill(['legal_name' => 'Công ty TNHH D&T Tên Mới', 'tax_id' => '0399999999'])->save();

        foreach ([PageKind::About, PageKind::Privacy] as $kind) {
            $this->get('https://vpptrangthietbi.com.vn'.$kind->path())
                ->assertSee('Công ty TNHH D&amp;T Tên Mới', escape: false)
                ->assertSee('0399999999')
                ->assertDontSee('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', escape: false)
                ->assertDontSee('0312345678');
        }
    }

    public function test_a_site_whose_theme_folder_is_missing_falls_back_to_the_default_theme(): void
    {
        $this->publish($this->vpp, PageKind::Contact);
        $this->vpp->forceFill(['theme' => 'theme-da-bi-xoa'])->save();

        $this->get('https://vpptrangthietbi.com.vn/lien-he')->assertOk()->assertSee('class="quote-form"', escape: false);
    }
}
