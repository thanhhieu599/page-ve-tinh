<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\OutboundClick;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

/**
 * Quản trị phần "sản phẩm dẫn về web chính": link trangthietbi.vn bắt buộc, tab sản phẩm thiếu link,
 * đường dẫn tự tạo (không còn ô slug), báo cáo lượt bấm sang web chính theo site/sản phẩm/trang.
 */
class MainSiteLinkAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private const ADMIN = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn';

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
    }

    public function test_the_main_site_link_is_required_and_the_url_name_is_made_automatically(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());
        Product::factory()->for($this->vpp)->create(['name' => 'Bút bi TL-027', 'slug' => 'but-bi-tl-027', 'sku' => 'CU']);

        Livewire::test(CreateProduct::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['name' => 'Bút bi TL-027', 'sku' => 'TL-027', 'buy_url' => ''])
            ->call('create')
            ->assertHasFormErrors(['buy_url' => 'required']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Bút bi TL-027', 'sku' => 'TL-027', 'buy_url' => 'https://trangthietbi.vn/but-bi-tl-027'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('but-bi-tl-027-2', Product::query()->where('sku', 'TL-027')->value('slug'), 'Trùng tên vẫn có đường dẫn riêng.');
    }

    public function test_a_tab_lists_products_still_missing_their_main_site_link(): void
    {
        Product::factory()->for($this->vpp)->create(['name' => 'Chưa có link', 'buy_url' => null]);
        Product::factory()->for($this->vpp)->create(['name' => 'Đã có link', 'buy_url' => 'https://trangthietbi.vn/x']);

        $this->actingAs(User::factory()->writer()->hasAttached($this->vpp)->create())
            ->get(self::ADMIN.'/products?tab=missing-link')
            ->assertOk()
            ->assertSee('Thiếu link web chính')
            ->assertSee('Chưa có link')
            ->assertDontSee('Đã có link');
    }

    public function test_the_report_counts_clicks_to_the_main_site_by_site_product_and_page(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);
        $paper = Product::factory()->for($this->vpp)->create(['name' => 'Giấy Double A A4']);
        $pen = Product::factory()->for($this->vpp)->create(['name' => 'Bút bi TL-027']);
        $click = fn (Site $site, ?Product $product, string $page, string $at) => OutboundClick::withoutGlobalScopes()->forceCreate([
            'site_id' => $site->id, 'product_id' => $product?->id, 'source_path' => $page, 'placement' => 'the-sp', 'clicked_at' => Carbon::parse($at, 'UTC'),
        ]);
        $click($this->vpp, $paper, '/kien-thuc/chon-giay', '2026-11-02 03:00:00');
        $click($this->vpp, $paper, '/kien-thuc/chon-giay', '2026-11-03 03:00:00');
        $click($this->vpp, $pen, '/danh-muc/but', '2026-11-04 03:00:00');
        $click($this->vpp, $pen, '/danh-muc/but', '2026-10-20 03:00:00'); // ngoài khoảng
        $click($ppe, null, '/', '2026-11-05 03:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/bao-cao?from=2026-11-01&to=2026-11-07')
            ->assertOk()
            ->assertSeeText('Bấm sang web chính')
            ->assertSeeTextInOrder(['Bấm sang web chính: Văn phòng phẩm D&T', 'Giấy Double A A4', '2', 'Bút bi TL-027', '1'])
            ->assertSeeTextInOrder(['/kien-thuc/chon-giay', '2', '/danh-muc/but', '1']);
    }
}
