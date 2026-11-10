<?php

namespace Tests\Feature\PublicSite;

use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cache toàn trang: trang công khai được lưu sẵn để tải nhanh, nhưng khách luôn thấy nội dung
 * mới sau khi biên tập lưu, và không bao giờ thấy dữ liệu riêng của khách khác.
 */
class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['responsecache.enabled' => true]);
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
    }

    public function test_a_public_page_is_served_from_the_cache_on_the_next_visit(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-a4', 'title' => 'Tiêu đề ban đầu']);
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4')->assertSee('Tiêu đề ban đầu');

        // Sửa thẳng trong database (không qua trang quản trị): trang vẫn là bản đã lưu sẵn.
        DB::table('posts')->where('id', $post->id)->update(['title' => 'Sửa ngoài hệ thống']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4')->assertSee('Tiêu đề ban đầu');
    }

    public function test_saving_content_shows_the_new_version_right_away_on_every_page(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-a4', 'title' => 'Tiêu đề ban đầu']);
        $this->get('https://vpptrangthietbi.com.vn/')->assertSee('Tiêu đề ban đầu');
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4')->assertSee('Tiêu đề ban đầu');

        $post->update(['title' => 'Tiêu đề đã sửa']);

        $this->get('https://vpptrangthietbi.com.vn/')->assertSee('Tiêu đề đã sửa');
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4')->assertSee('Tiêu đề đã sửa');
    }

    public function test_sites_never_share_cached_pages(): void
    {
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->assertSee('Văn phòng phẩm D&amp;T', escape: false);
        $this->get('https://ppetrangthietbi.vn/yeu-cau-bao-gia')->assertSee('PPE D&amp;T', escape: false)->assertDontSee('Văn phòng phẩm D&amp;T', escape: false);
    }

    public function test_one_visitors_form_input_is_never_shown_to_another_visitor(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia');

        // Khách A gửi thiếu thông tin: trang hiện lại kèm dữ liệu A vừa nhập và thông báo lỗi.
        $this->from('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->post('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia', ['name' => 'Nguyễn Văn Bí Mật', 'company' => 'Công ty A'])
            ->assertRedirect();
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->assertSee('Nguyễn Văn Bí Mật');

        // Khách B (phiên khác) không được thấy dữ liệu của A.
        $this->flushSession();
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertOk()
            ->assertDontSee('Nguyễn Văn Bí Mật')
            ->assertDontSee('form-errors');
    }

    public function test_tracking_parameters_share_one_cached_page_without_leaking_into_the_form(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia?utm_source=zalo&utm_campaign=thang-11');
        $this->flushSession();

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('name="page_url" value="/yeu-cau-bao-gia"', escape: false)
            ->assertDontSee('zalo');
    }

    public function test_the_thank_you_page_is_never_cached(): void
    {
        $this->vpp->update(['settings' => ['ga4_id' => 'G-VPP12345']]);
        $this->withSession(['quote_submitted' => true])->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');
        $this->flushSession();

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')->assertDontSee('generate_lead');
    }

    public function test_an_expired_price_disappears_at_midnight_even_if_the_page_was_cached(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00', 'UTC'));
        Product::factory()->for($this->vpp)->create(['slug' => 'giay-double-a'])->setPrice(89000);

        $this->travelTo(Carbon::parse('2026-11-15 16:00:00', 'UTC')); // 23:00 ngày 15/11 VN: ngày thứ 14, giá còn hạn
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a')->assertSee('89.000 đ');

        $this->travelTo(Carbon::parse('2026-11-15 17:05:00', 'UTC')); // 00:05 ngày 16/11 VN: giá hết hạn
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a')->assertSee('Liên hệ báo giá')->assertDontSee('89.000');
    }
}
