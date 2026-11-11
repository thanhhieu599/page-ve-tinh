<?php

namespace Tests\Feature\PublicSite;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class CategoryProductsTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_a_category_page_shows_its_products_and_those_of_its_subcategories(): void
    {
        $office = Category::factory()->for($this->vpp)->create(['slug' => 'dung-cu-van-phong', 'name' => 'Dụng cụ văn phòng']);
        $staplers = Category::factory()->for($this->vpp)->childOf($office)->create(['slug' => 'bam-kim', 'name' => 'Bấm kim']);
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy']);
        Product::factory()->for($this->vpp)->create(['name' => 'Kéo văn phòng Deli 6032', 'category_id' => $office->id]);
        Product::factory()->for($this->vpp)->create(['name' => 'Máy bấm kim E0395', 'category_id' => $staplers->id]);
        Product::factory()->for($this->vpp)->create(['name' => 'Bấm kim ngừng bán', 'category_id' => $staplers->id, 'is_active' => false]);
        Product::factory()->for($this->vpp)->create(['name' => 'Giấy A4 Double A', 'category_id' => $paper->id]);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Cách chọn máy bấm kim'])->categories()->attach($staplers);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/dung-cu-van-phong')
            ->assertOk()
            ->assertSeeText('Kéo văn phòng Deli 6032')
            ->assertSeeText('Máy bấm kim E0395')
            ->assertSeeText('Cách chọn máy bấm kim')
            ->assertDontSeeText('Bấm kim ngừng bán')
            ->assertDontSeeText('Giấy A4 Double A');
    }

    public function test_a_category_that_only_has_products_is_in_the_menu_and_the_sitemap(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy in văn phòng']);
        Category::factory()->for($this->vpp)->create(['slug' => 'trong', 'name' => 'Danh mục trống']);
        Product::factory()->for($this->vpp)->create(['category_id' => $paper->id]);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('href="/danh-muc/giay"', escape: false)
            ->assertDontSee('href="/danh-muc/trong"', escape: false);
        $this->assertStringContainsString('<loc>https://vpptrangthietbi.com.vn/danh-muc/giay</loc>', (string) $this->get('https://vpptrangthietbi.com.vn/sitemap.xml')->getContent());
    }

    public function test_an_editor_files_a_product_under_a_category_of_this_site_only(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy']);
        $foreign = Category::factory()->for(Site::factory()->create(['domain' => 'ppetrangthietbi.vn']))->create(['name' => 'Găng tay']);
        $product = Product::factory()->for($this->vpp)->create();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => $foreign->id])
            ->call('save')
            ->assertHasFormErrors(['category_id']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => $paper->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($paper->id, $product->fresh()->category_id);
    }
}
