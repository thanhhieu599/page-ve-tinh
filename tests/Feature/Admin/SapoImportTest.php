<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductSource;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Support\ProductSync\ProductRecord;
use App\Support\ProductSync\ProductSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class SapoImportTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());
    }

    /** @param  list<list<string>>  $rows */
    private function csv(array $rows, string $delimiter = ';'): UploadedFile
    {
        $lines = array_map(fn (array $row) => implode($delimiter, array_map(
            fn (string $cell) => '"'.str_replace('"', '""', $cell).'"', $row,
        )), $rows);

        return UploadedFile::fake()->createWithContent('sapo.csv', "\xEF\xBB\xBF".implode("\r\n", $lines));
    }

    private function import(UploadedFile $file, bool $fullFile = false): Testable
    {
        return Livewire::test(ListProducts::class)
            ->callAction('importSapo', data: ['file' => $file, 'full_file' => $fullFile])
            ->assertHasNoActionErrors();
    }

    private const HEADER = ['Tên sản phẩm', 'Mã SKU', 'Giá bán lẻ', 'Link mua', 'Thông số: Định lượng', 'Thông số: Quy cách', 'Trạng thái'];

    public function test_importing_a_sapo_csv_creates_products_shown_on_the_site(): void
    {
        $this->import($this->csv([
            self::HEADER,
            ['Giấy Double A A4 80gsm', 'DA-A4-80', '89.000', 'https://trangthietbi.vn/giay-double-a', '80gsm', '500 tờ/ram', 'Đang bán'],
            ['Bút bi Thiên Long TL-027', 'TL-027', '4500', '', '', 'Hộp 20 cây', 'Đang bán'],
        ]))->assertNotified('Nhập CSV xong: 2 tạo mới, 0 cập nhật, 0 ẩn, 0 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertOk()
            ->assertSee('DA-A4-80')
            ->assertSee('89.000 đ')
            ->assertSee('Định lượng')->assertSee('80gsm')
            ->assertSee('href="https://trangthietbi.vn/giay-double-a"', escape: false);
        $this->get('https://vpptrangthietbi.com.vn/san-pham/but-bi-thien-long-tl-027')
            ->assertOk()->assertSee('4.500 đ')->assertSee('Hộp 20 cây');
    }

    public function test_reimporting_updates_sapo_fields_without_duplicating_or_touching_site_content(): void
    {
        $this->import($this->csv([self::HEADER, ['Giấy Double A A4 80gsm', 'DA-A4-80', '89.000', '', '80gsm', '', 'Đang bán']]));
        $product = Product::query()->where('sku', 'DA-A4-80')->firstOrFail();
        $product->update(['description' => 'Mô tả riêng của site VPP.']);
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay']);
        $post->products()->attach($product);

        $this->import($this->csv([self::HEADER, ['Giấy Double A A4 80gsm (thùng 5 ram)', 'DA-A4-80', '91.000', '', '80gsm', '', 'Đang bán']]))
            ->assertNotified('Nhập CSV xong: 0 tạo mới, 1 cập nhật, 0 ẩn, 0 dòng lỗi');

        $this->assertSame(1, Product::query()->where('sku', 'DA-A4-80')->count());
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertOk()
            ->assertSee('Giấy Double A A4 80gsm (thùng 5 ram)')
            ->assertSee('91.000 đ')
            ->assertSee('Mô tả riêng của site VPP.');
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chon-giay')->assertSee('Giấy Double A A4 80gsm (thùng 5 ram)');
    }

    public function test_discontinued_product_is_hidden_and_its_url_returns_410(): void
    {
        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán']]));

        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Ngừng kinh doanh']]))
            ->assertNotified('Nhập CSV xong: 0 tạo mới, 1 cập nhật, 1 ẩn, 0 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')->assertStatus(410);
    }

    public function test_hidden_product_redirects_to_its_replacement_when_one_is_chosen(): void
    {
        $this->import($this->csv([self::HEADER,
            ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Ngừng kinh doanh'],
            ['Bìa còng 7cm mẫu mới', 'BC-7N', '27000', '', '', '', 'Đang bán'],
        ]));
        $old = Product::query()->where('sku', 'BC-7')->firstOrFail();
        $new = Product::query()->where('sku', 'BC-7N')->firstOrFail();

        Livewire::test(EditProduct::class, ['record' => $old->getRouteKey()])
            ->fillForm(['replacement_product_id' => $new->id])->call('save')->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')
            ->assertStatus(301)->assertRedirect('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm-mau-moi');
    }

    public function test_product_back_on_sale_in_sapo_reappears_but_a_manually_hidden_one_stays_hidden(): void
    {
        $this->import($this->csv([self::HEADER,
            ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Ngừng kinh doanh'],
            ['Kẹp giấy', 'KG-1', '5000', '', '', '', 'Đang bán'],
        ]));
        Product::query()->where('sku', 'KG-1')->firstOrFail()->update(['is_active' => false]);

        $this->import($this->csv([self::HEADER,
            ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán'],
            ['Kẹp giấy', 'KG-1', '5000', '', '', '', 'Đang bán'],
        ]));

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')->assertOk();
        $this->get('https://vpptrangthietbi.com.vn/san-pham/kep-giay')->assertStatus(410);
    }

    public function test_full_file_mode_hides_products_missing_from_the_file_but_not_hand_made_ones(): void
    {
        $this->import($this->csv([self::HEADER,
            ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán'],
            ['Kẹp giấy', 'KG-1', '5000', '', '', '', 'Đang bán'],
        ]));
        Product::factory()->for($this->vpp)->create(['name' => 'Quà tặng khắc logo', 'slug' => 'qua-tang-khac-logo']);

        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán']]), fullFile: true)
            ->assertNotified('Nhập CSV xong: 0 tạo mới, 1 cập nhật, 1 ẩn, 0 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/kep-giay')->assertStatus(410);
        $this->get('https://vpptrangthietbi.com.vn/san-pham/qua-tang-khac-logo')->assertOk();
    }

    public function test_bad_rows_are_reported_with_their_line_number_and_good_rows_still_import(): void
    {
        $this->import($this->csv([self::HEADER,
            ['Bút bi', '', '4500', '', '', '', 'Đang bán'],
            ['Bút chì', 'BC-2B', 'liên hệ', '', '', '', 'Đang bán'],
            ['Bút dạ', 'BD-1', '9000', 'https://doi-thu.vn/but-da', '', '', 'Đang bán'],
            ['Thước kẻ 30cm', 'TK-30', '7000', '', '', '', 'Đang bán'],
        ]))->assertNotified('Nhập CSV xong: 1 tạo mới, 0 cập nhật, 0 ẩn, 3 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/thuoc-ke-30cm')->assertOk();
        $this->get('https://vpptrangthietbi.com.vn/san-pham/but-da')->assertNotFound();
    }

    public function test_comma_separated_file_without_bom_is_read_too(): void
    {
        $file = UploadedFile::fake()->createWithContent('sapo.csv', "Ten san pham,SKU,Gia ban le\nThuoc ke 20cm,TK-20,\"6,000\"\n");

        $this->import($file)->assertNotified('Nhập CSV xong: 1 tạo mới, 0 cập nhật, 0 ẩn, 0 dòng lỗi');
        $this->get('https://vpptrangthietbi.com.vn/san-pham/thuoc-ke-20cm')->assertSee('6.000 đ');
    }

    public function test_the_same_sync_rules_serve_a_future_sapo_api_source(): void
    {
        $report = app(ProductSync::class)->sync($this->vpp->id, [
            1 => new ProductRecord(sku: 'API-1', name: 'Sổ tay A5 từ API', price: 32000),
        ], ProductSource::Api);

        $this->assertSame(1, $report->created);
        $this->get('https://vpptrangthietbi.com.vn/san-pham/so-tay-a5-tu-api')->assertOk()->assertSee('32.000 đ');
    }

    public function test_the_downloadable_template_imports_cleanly(): void
    {
        $download = Livewire::test(ListProducts::class)->callAction('downloadTemplate');
        $download->assertFileDownloaded('mau-nhap-san-pham.csv');
        $content = base64_decode($download->effects['download']['content']);

        $this->import(UploadedFile::fake()->createWithContent('mau.csv', $content))
            ->assertNotified('Nhập CSV xong: 1 tạo mới, 0 cập nhật, 0 ẩn, 0 dòng lỗi');
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')->assertOk()->assertSee('500 tờ/ram');
    }

    public function test_full_file_mode_never_hides_anything_when_the_file_is_unreadable_or_empty(): void
    {
        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán']]));

        $this->import(UploadedFile::fake()->createWithContent('sai.csv', "Ten,Gia\nBia cong,25000\n"), fullFile: true);
        $this->import($this->csv([self::HEADER]), fullFile: true);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')->assertOk();
    }

    public function test_full_file_mode_keeps_a_product_whose_row_has_an_error(): void
    {
        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', 'Đang bán']]));

        $this->import($this->csv([self::HEADER, ['Bìa còng 7cm', 'BC-7', 'liên hệ', '', '', '', 'Đang bán']]), fullFile: true)
            ->assertNotified('Nhập CSV xong: 0 tạo mới, 0 cập nhật, 0 ẩn, 1 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')->assertOk();
    }

    public function test_prices_with_decimals_or_separators_are_read_correctly(): void
    {
        $this->import($this->csv([self::HEADER,
            ['A', 'P-1', '89000.00', '', '', '', ''],
            ['B', 'P-2', '89.000,00', '', '', '', ''],
            ['C', 'P-3', '1,250,000', '', '', '', ''],
            ['D', 'P-4', '89.000 đ', '', '', '', ''],
        ]));

        $this->get('https://vpptrangthietbi.com.vn/san-pham/a')->assertSee('89.000 đ');
        $this->get('https://vpptrangthietbi.com.vn/san-pham/b')->assertSee('89.000 đ');
        $this->get('https://vpptrangthietbi.com.vn/san-pham/c')->assertSee('1.250.000 đ');
        $this->get('https://vpptrangthietbi.com.vn/san-pham/d')->assertSee('89.000 đ');
    }

    public function test_duplicate_sku_in_one_file_is_reported_and_the_first_row_wins(): void
    {
        $this->import($this->csv([self::HEADER,
            ['Bìa còng 7cm', 'BC-7', '25000', '', '', '', ''],
            ['Bìa còng 7cm (dòng lặp)', 'BC-7', '99000', '', '', '', ''],
        ]))->assertNotified('Nhập CSV xong: 1 tạo mới, 0 cập nhật, 0 ẩn, 1 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cong-7cm')->assertSee('25.000 đ')->assertDontSee('99.000');
    }

    public function test_new_product_already_discontinued_counts_as_created_only(): void
    {
        $this->import($this->csv([self::HEADER, ['Bìa cũ', 'BC-OLD', '25000', '', '', '', 'Ngừng giao dịch']]))
            ->assertNotified('Nhập CSV xong: 1 tạo mới, 0 cập nhật, 0 ẩn, 0 dòng lỗi');

        $this->get('https://vpptrangthietbi.com.vn/san-pham/bia-cu')->assertStatus(410);
    }

    public function test_sync_refuses_to_run_without_a_site(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ProductSync::class)->sync(0, [1 => new ProductRecord(sku: 'X', name: 'X')], ProductSource::Csv);
    }
}
