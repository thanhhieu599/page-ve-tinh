<?php

namespace Database\Seeders;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\ContactRole;
use App\Enums\Crm\CustomerType;
use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\TaskType;
use App\Models\Activity;
use App\Models\Brand;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu CRM MẪU để xem giao diện khi chạy local (tên có chữ "Mẫu"; không tạo tài khoản đăng nhập).
 * Chạy: php artisan db:seed --class=Database\Seeders\CrmDemoSeeder
 */
class CrmDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::salesPeople()->first();
        CompanyProfile::stored()->fill([
            'quote_vat_rate' => CompanyProfile::stored()->quote_vat_rate ?? 8, 'quote_valid_days' => 15, 'payment_due_days' => 30,
            'default_reorder_cycle_days' => 30,
            'quote_delivery_terms' => 'Giao tận kho nhà máy trong 3–5 ngày làm việc kể từ khi xác nhận đơn.',
            'quote_payment_terms' => 'Chuyển khoản trong 30 ngày kể từ ngày xuất hoá đơn VAT.',
        ])->save();
        foreach (['TOWA', '3M', 'Deli', 'Thiên Long', 'Double A'] as $brand) {
            Brand::query()->firstOrCreate(['name' => $brand]);
        }

        $samples = [
            ['Nhà máy Điện tử Mẫu A (FDI)', CustomerType::Fdi, 'Điện tử', 'KCN Yên Phong', 'Bắc Ninh', 'mau-a.example', 'Chị Lan (mẫu)', ContactRole::Purchasing,
                [['Găng tay chống tĩnh điện (mẫu)', 'đôi', 2000, 18000], ['Khẩu trang y tế 4 lớp (mẫu)', 'hộp', 300, 35000]]],
            ['Công ty Thực phẩm Mẫu B', CustomerType::Domestic, 'Thực phẩm', 'KCN Tân Uyên', 'Bình Dương', 'mau-b.example', 'Anh Hùng (mẫu)', ContactRole::Qa,
                [['Găng tay nitrile thực phẩm (mẫu)', 'hộp', 120, 145000], ['Mũ trùm tóc (mẫu)', 'gói', 200, 42000]]],
            ['Nhà máy Thép Mẫu C', CustomerType::Domestic, 'Thép / luyện kim', 'KCN Phú Mỹ', 'Bà Rịa - Vũng Tàu', 'mau-c.example', 'Chị Mai (mẫu)', ContactRole::Admin,
                [['Giấy A4 Double A 80gsm (mẫu)', 'ram', 100, 89000], ['Bút bi TL-027 (mẫu)', 'hộp', 20, 76000]]],
        ];

        foreach ($samples as $i => [$name, $type, $industry, $zone, $province, $domain, $contactName, $role, $lines]) {
            $customer = Customer::query()->firstOrCreate(['name' => $name], [
                'type' => $type, 'industry' => $industry, 'industrial_zone' => $zone, 'province' => $province,
                'email_domain' => $domain, 'owner_id' => $owner?->id, 'reorder_cycle_days' => 30,
            ]);
            if ($customer->contacts()->exists()) {
                continue;
            }
            $contact = $customer->contacts()->create(['name' => $contactName, 'role' => $role, 'phone' => '090000000'.$i, 'email' => "mua@{$domain}", 'is_primary' => true]);

            $quote = Quote::query()->create(['customer_id' => $customer->id, 'contact_id' => $contact->id, 'owner_id' => $owner?->id, 'notes' => 'Báo giá mẫu.']);
            foreach ($lines as $sort => [$item, $unit, $qty, $price]) {
                $quote->items()->create(['name' => $item, 'unit' => $unit, 'quantity' => $qty, 'unit_price' => $price, 'sort' => $sort]);
            }
            $quote->recalculateTotals();

            if ($i < 2) {
                $order = $quote->markWon();
                $order->forceFill(['ordered_at' => now()->subDays(40 - $i * 10)->toDateString(), 'due_date' => now()->subDays(10 - $i * 15)->toDateString()])->save();
                if ($i === 0) {
                    $order->forceFill(['status' => OrderStatus::Completed, 'delivered_at' => now()->subDays(25)->toDateString(), 'paid_amount' => intdiv($order->total, 2)])->save();
                }
            }

            Activity::query()->create(['customer_id' => $customer->id, 'user_id' => $owner?->id, 'type' => ActivityType::Call, 'content' => 'Gọi giới thiệu năng lực cung cấp (mẫu).', 'happened_at' => now()->subDays(3)]);
            Task::query()->create(['title' => 'Gọi lại xác nhận báo giá '.$quote->number.' (mẫu)', 'type' => TaskType::FollowUp, 'due_at' => CarbonImmutable::parse('today 15:00', config('app.display_timezone'))->utc(),
                'assigned_to' => $owner?->id, 'created_by' => $owner?->id, 'customer_id' => $customer->id]);
        }
    }
}
