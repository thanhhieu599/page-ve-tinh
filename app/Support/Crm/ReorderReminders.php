<?php

namespace App\Support\Crm;

use App\Enums\Crm\TaskType;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Nhắc tái đặt hàng: khách đến kỳ mua lại trong DAYS_AHEAD ngày → mỗi khách một việc "Nhắc tái đặt hàng"
 * cho người phụ trách (không tạo trùng khi việc cũ chưa xong). Chạy hằng ngày (routes/console.php).
 */
final class ReorderReminders
{
    public const DAYS_AHEAD = 7;

    public static function run(): int
    {
        $created = 0;
        Customer::query()->whereNotNull('next_reorder_at')
            ->whereDate('next_reorder_at', '<=', now()->addDays(self::DAYS_AHEAD))
            ->each(function (Customer $customer) use (&$created): void {
                $open = $customer->tasks()->where('type', TaskType::Reorder)->whereNull('done_at')->exists();
                if (! $open) {
                    self::taskFor($customer, TaskType::Reorder, 'Nhắc tái đặt hàng: '.$customer->name, self::nineAm($customer->next_reorder_at->toDateString()));
                    $created++;
                }
            });

        return $created;
    }

    public static function taskFor(Customer $customer, TaskType $type, string $title, $dueAt = null): Task
    {
        $assignee = $customer->owner_id ?? auth()->id() ?? User::salesPeople()->value('id');
        $task = Task::query()->create([
            'title' => $title, 'type' => $type, 'due_at' => $dueAt ?? self::nineAm(now(config('app.display_timezone'))->addDay()->toDateString()),
            'assigned_to' => $assignee, 'customer_id' => $customer->id,
        ]);
        if (($to = User::query()->find($assignee)) && $to->id !== auth()->id()) {
            CrmNotifier::taskAssigned($task, $to);
        }

        return $task;
    }

    /** 9:00 sáng giờ Việt Nam của một ngày (lưu UTC). */
    public static function nineAm(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' 09:00:00', config('app.display_timezone'))->utc();
    }

    /** Báo giá mới, chép dòng hàng của đơn gần nhất (giá giữ như đơn cũ để nhân viên chỉnh). */
    public static function requote(Customer $customer): Quote
    {
        $last = $customer->orders()->latest('ordered_at')->latest('id')->firstOrFail();
        $quote = Quote::query()->create([
            'customer_id' => $customer->id, 'contact_id' => $last->contact_id, 'site_id' => $last->site_id,
            'owner_id' => $customer->owner_id ?? auth()->id(), 'notes' => 'Báo giá tái đặt hàng theo đơn '.$last->number.'.',
        ]);
        foreach ($last->items as $item) {
            $quote->items()->create($item->only(['product_id', 'sku', 'name', 'unit', 'quantity', 'unit_price', 'sort']));
        }
        $quote->recalculateTotals();

        return $quote;
    }
}
