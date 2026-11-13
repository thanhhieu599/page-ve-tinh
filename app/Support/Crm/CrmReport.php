<?php

namespace App\Support\Crm;

use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\QuoteStatus;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu CRM trong một khoảng ngày (giờ Việt Nam), theo quyền người xem
 * (nhân viên: phần của mình; trưởng phòng/Admin: toàn bộ).
 */
final class CrmReport
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to, private readonly User $user) {}

    public static function thisMonth(User $user): self
    {
        $now = CarbonImmutable::now(self::tz());

        return new self($now->startOfMonth(), $now->endOfDay(), $user);
    }

    public static function tz(): string
    {
        return (string) config('app.display_timezone');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function utc(): array
    {
        return [$this->from->setTimezone(self::tz())->startOfDay()->utc(), $this->to->setTimezone(self::tz())->endOfDay()->utc()];
    }

    /** @return Builder<Lead> */
    private function leads(): Builder
    {
        return Lead::withoutGlobalScopes()->visibleTo($this->user)->whereBetween('leads.created_at', $this->utc());
    }

    /** @return Builder<Order> đơn chưa huỷ, đặt trong kỳ */
    private function orders(): Builder
    {
        return Order::query()->visibleTo($this->user)->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('ordered_at', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /** @return array{leads: int, open_quotes: int, pipeline: int, revenue: int, orders: int, outstanding: int, overdue: int, tasks_due: int} */
    public function kpis(): array
    {
        $open = Quote::query()->visibleTo($this->user)->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Negotiating]);
        $active = Order::query()->visibleTo($this->user)->where('status', '!=', OrderStatus::Cancelled);

        return [
            'leads' => $this->leads()->count(),
            'open_quotes' => (clone $open)->count(),
            'pipeline' => (int) (clone $open)->sum('total'),
            'revenue' => (int) $this->orders()->sum('total'),
            'orders' => $this->orders()->count(),
            'outstanding' => (int) (clone $active)->sum(DB::raw('GREATEST(total - paid_amount, 0)')),
            'overdue' => (int) Order::query()->visibleTo($this->user)->overdue()->sum(DB::raw('GREATEST(total - paid_amount, 0)')),
            'tasks_due' => Task::query()->where('assigned_to', $this->user->id)->dueToday()->count(),
        ];
    }

    /** @return Collection<int, array{name: string, leads: int, won: int, conversion: ?float, revenue: int}> */
    public function bySite(): Collection
    {
        $leads = $this->leads()->groupBy('site_id')->selectRaw('site_id, COUNT(*) AS total, SUM(status = ?) AS won', [LeadStatus::Won->value])->get()->keyBy('site_id');
        $revenue = $this->orders()->groupBy('site_id')->selectRaw('site_id, SUM(total) AS revenue')->pluck('revenue', 'site_id');
        $names = Site::query()->pluck('name', 'id')->all() + ['' => 'Nhập tay / không qua website'];

        return collect(array_unique([...$leads->keys()->all(), ...$revenue->keys()->all()]))
            ->map(function ($siteId) use ($leads, $revenue, $names): array {
                $total = (int) ($leads[$siteId]->total ?? 0);
                $won = (int) ($leads[$siteId]->won ?? 0);

                return ['name' => $names[$siteId ?? ''] ?? '—', 'leads' => $total, 'won' => $won,
                    'conversion' => $total > 0 ? round($won * 100 / $total, 1) : null, 'revenue' => (int) ($revenue[$siteId] ?? 0)];
            })->sortByDesc('revenue')->values();
    }

    /** @return Collection<int, array{name: string, leads: int, quotes: int, won: int, revenue: int, pipeline: int}> */
    public function bySalesperson(): Collection
    {
        [$start, $end] = $this->utc();
        $users = $this->user->isSalesManager() ? User::salesPeople()->get() : collect([$this->user]);

        return $users->map(fn (User $u): array => [
            'name' => $u->name,
            'leads' => Lead::withoutGlobalScopes()->where('assigned_to', $u->id)->whereBetween('created_at', [$start, $end])->count(),
            'quotes' => Quote::query()->where('owner_id', $u->id)->whereBetween('created_at', [$start, $end])->count(),
            'won' => Quote::query()->where('owner_id', $u->id)->where('status', QuoteStatus::Won)->whereBetween('decided_at', [$start, $end])->count(),
            'revenue' => (int) Order::query()->where('owner_id', $u->id)->where('status', '!=', OrderStatus::Cancelled)
                ->whereBetween('ordered_at', [$this->from->toDateString(), $this->to->toDateString()])->sum('total'),
            'pipeline' => (int) Quote::query()->where('owner_id', $u->id)->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Negotiating])->sum('total'),
        ])->sortByDesc('revenue')->values();
    }

    /** @return Collection<int, array{status: QuoteStatus, count: int, value: int}> phễu báo giá tạo trong kỳ */
    public function pipeline(): Collection
    {
        $rows = Quote::query()->visibleTo($this->user)->whereBetween('created_at', $this->utc())
            ->groupBy('status')->selectRaw('status, COUNT(*) AS n, SUM(total) AS v')->get()->keyBy(fn ($r) => $r->status->value);

        return collect(QuoteStatus::cases())->map(fn (QuoteStatus $s): array => [
            'status' => $s, 'count' => (int) ($rows[$s->value]->n ?? 0), 'value' => (int) ($rows[$s->value]->v ?? 0),
        ]);
    }

    /** @return Collection<int, array{reason: string, count: int, value: int}> */
    public function lostReasons(): Collection
    {
        return Quote::query()->visibleTo($this->user)->where('status', QuoteStatus::Lost)->whereBetween('decided_at', $this->utc())
            ->groupBy('lost_reason')->selectRaw('lost_reason, COUNT(*) AS n, SUM(total) AS v')->orderByDesc('n')->get()
            ->map(fn ($r): array => ['reason' => (string) $r->lost_reason, 'count' => (int) $r->n, 'value' => (int) $r->v]);
    }

    /** @return array<string, int> doanh số 12 tháng gần nhất ("11/2026" => tổng) */
    public static function monthlyRevenue(User $user, int $months = 12): array
    {
        $start = CarbonImmutable::now(self::tz())->startOfMonth()->subMonths($months - 1);
        $totals = Order::query()->visibleTo($user)->where('status', '!=', OrderStatus::Cancelled)->whereDate('ordered_at', '>=', $start->toDateString())
            ->get(['ordered_at', 'total'])->groupBy(fn (Order $o) => $o->ordered_at->format('m/Y'))->map->sum('total');

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->addMonths($i)->format('m/Y');
            $out[$key] = (int) ($totals[$key] ?? 0);
        }

        return $out;
    }
}
