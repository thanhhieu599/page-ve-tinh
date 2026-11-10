<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    private function page(string $slug): TestResponse
    {
        return $this->get('https://vpptrangthietbi.com.vn/san-pham/'.$slug);
    }

    public function test_writer_adds_a_product_with_a_price_dated_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 03:00:00', 'UTC'));
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Bút bi Thiên Long TL-027',
                'sku' => 'TL-027',
                'price' => 4500,
                'buy_url' => 'https://trangthietbi.vn/but-bi-tl-027',
                'specs' => ['Màu mực' => 'Xanh', 'Ngòi' => '0.5mm'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->page('but-bi-thien-long-tl-027')
            ->assertSee('4.500 đ')
            ->assertSee('Cập nhật ngày 10/11/2026.')
            ->assertSee('Ngòi');
    }

    public function test_changing_the_price_refreshes_its_date_but_editing_other_fields_does_not(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
        $product = Product::factory()->for($this->vpp)->create(['slug' => 'but-bi']);
        $product->setPrice(4500);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        $this->travelTo(Carbon::parse('2026-11-05 03:00:00', 'UTC'));
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Bút bi TL-027 (hộp 20 cây)'])->call('save')->assertHasNoFormErrors();
        $this->page('but-bi')->assertSee('Cập nhật ngày 01/11/2026.');

        $this->travelTo(Carbon::parse('2026-11-08 03:00:00', 'UTC'));
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['price' => 4800])->call('save')->assertHasNoFormErrors();
        $this->page('but-bi')->assertSee('4.800 đ')->assertSee('Cập nhật ngày 08/11/2026.');
    }

    public function test_confirming_the_price_keeps_it_on_the_site_for_another_14_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
        $product = Product::factory()->for($this->vpp)->create(['slug' => 'but-bi']);
        $product->setPrice(4500);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        $this->travelTo(Carbon::parse('2026-11-14 03:00:00', 'UTC'));
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->callAction('confirmPrice');

        $this->travelTo(Carbon::parse('2026-11-20 03:00:00', 'UTC'));
        $this->page('but-bi')->assertSee('4.500 đ')->assertSee('Cập nhật ngày 14/11/2026.');
    }

    public function test_price_tabs_list_products_about_to_expire_and_already_expired(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
        Product::factory()->for($this->vpp)->create(['name' => 'Giá cũ đã hết hạn'])->setPrice(1000);
        $this->travelTo(Carbon::parse('2026-11-05 03:00:00', 'UTC'));
        Product::factory()->for($this->vpp)->create(['name' => 'Giá sắp hết hạn'])->setPrice(2000);
        $this->travelTo(Carbon::parse('2026-11-15 03:00:00', 'UTC'));
        Product::factory()->for($this->vpp)->create(['name' => 'Giá mới cập nhật'])->setPrice(3000);
        $this->travelTo(Carbon::parse('2026-11-16 03:00:00', 'UTC'));

        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();
        $base = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/products?tab=';

        $this->actingAs($writer)->get($base.'expiring')
            ->assertSee('Giá sắp hết hạn')->assertDontSee('Giá cũ đã hết hạn')->assertDontSee('Giá mới cập nhật');
        $this->actingAs($writer)->get($base.'expired')
            ->assertSee('Giá cũ đã hết hạn')->assertDontSee('Giá sắp hết hạn')->assertDontSee('Giá mới cập nhật');
    }

    public function test_buy_link_must_point_to_trangthietbi_vn_over_https(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        foreach (['http://trangthietbi.vn/x', 'https://doi-thu.vn/x', 'https://trangthietbi.vn.evil.com/x', 'javascript:alert(1)'] as $url) {
            Livewire::test(CreateProduct::class)
                ->fillForm(['name' => 'Sản phẩm', 'sku' => 'SP-1', 'buy_url' => $url])
                ->call('create')
                ->assertHasFormErrors(['buy_url']);
        }
    }

    public function test_post_can_only_link_products_of_the_same_site(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        $foreign = Product::factory()->for($ppe)->create();
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Bài thử', 'content' => '<p>x</p>', 'products' => [$foreign->id]])
            ->call('create')
            ->assertHasFormErrors(['products.0']);
    }
}
