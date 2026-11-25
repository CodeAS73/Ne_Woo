<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CommerceReporting
{
    public function orders(User $user, string $scope): Builder
    {
        return match ($scope) {
            'purchase' => Order::query()->where('orders.buyer_user_id', $user->id),
            'sale' => Order::query()->where('orders.merchant_user_id', $user->id),
            'platform' => Order::query(),
        };
    }

    public function summary(User $user, string $scope, CarbonImmutable $month): array
    {
        $purchases = $scope === 'purchase';
        $amount = $purchases ? 'orders.total_minor' : 'orders.subtotal_minor';
        $paid = $this->orders($user, $scope)->confirmedPayments();
        $first = $month->subMonths(11);
        $case = 'CASE';
        $bindings = [];
        for ($period = $first; $period < $month; $period = $period->addMonth()) {
            $case .= ' WHEN payment.confirmed_at < ? THEN ?';
            $bindings[] = $period->addMonth()->utc()->toDateTimeString();
            $bindings[] = $period->format('Y-m');
        }
        $bindings[] = $month->format('Y-m');
        $periods = $this->between(clone $paid, $first, $month->addMonth())
            ->selectRaw("orders.currency, COUNT(*) as order_count, SUM($amount) as amount_minor")
            ->selectRaw('SUM(orders.total_minor) as purchase_minor')
            ->selectRaw($case.' ELSE ? END as period', $bindings)
            ->groupBy('period', 'orders.currency')->orderByDesc('period')->orderBy('orders.currency')->toBase()->get();

        $products = null;
        if (! $purchases) {
            $start = $month->utc()->toDateTimeString();
            $products = $this->between(clone $paid, $month->subMonth(), $month->addMonth())
                ->join('product_widgets as widget', 'widget.id', '=', 'orders.product_widget_id')
                ->select('widget.id', 'widget.name', 'orders.currency')
                ->selectRaw('SUM(CASE WHEN payment.confirmed_at >= ? THEN orders.quantity ELSE 0 END) as units', [$start])
                ->selectRaw('SUM(CASE WHEN payment.confirmed_at < ? THEN orders.quantity ELSE 0 END) as prior_units', [$start])
                ->selectRaw('SUM(CASE WHEN payment.confirmed_at >= ? THEN orders.subtotal_minor ELSE 0 END) as sales_minor', [$start])
                ->groupBy('widget.id', 'widget.name', 'orders.currency')
                ->orderByDesc('units')->orderBy('widget.id')->orderBy('orders.currency')
                ->toBase()->paginate(15, pageName: 'trends_page')->withQueryString();
        }

        return compact('scope', 'periods', 'products');
    }

    private function between(Builder $query, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $query->where('payment.confirmed_at', '>=', $from->utc())
            ->where('payment.confirmed_at', '<', $until->utc());
    }
}
