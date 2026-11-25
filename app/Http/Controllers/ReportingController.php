<?php

namespace App\Http\Controllers;

use App\Models\OrderWorkflowEvent;
use App\Services\CommerceReporting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportingController extends Controller
{
    public function dashboard(Request $request, CommerceReporting $reporting)
    {
        $internal = $request->routeIs('internal.*');
        $user = Auth::guard($internal ? 'internal' : 'web')->user();
        abort_unless($internal || ($user->account_type !== 'internal' && $user->hasPermission('orders.view_own')), 403);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-/']]);
        $month = CarbonImmutable::parse($data['month'] ?? now('Africa/Nairobi')->format('Y-m'), 'Africa/Nairobi')->startOfMonth();
        $scopes = $internal ? ['platform'] : ($user->account_type === 'merchant' ? ['purchase', 'sale'] : ['purchase']);
        $reports = array_map(fn ($scope) => $reporting->summary($user, $scope, $month), $scopes);

        return view('reports.dashboard', compact('internal', 'month', 'reports'));
    }

    public function ledger(Request $request, CommerceReporting $reporting)
    {
        $internal = $request->routeIs('internal.*');
        $settlement = $request->routeIs('*.settlements');
        $user = Auth::guard($internal ? 'internal' : 'web')->user();
        $scope = $internal ? 'platform' : $request->route('scope');
        abort_unless($internal || ($user->hasPermission('orders.view_own')
            && ($user->account_type === 'merchant' || ($user->account_type === 'buyer' && $scope === 'purchase'))), 403);

        $orders = $reporting->orders($user, $scope);
        $reference = $request->route('reference');
        if ($settlement) {
            $orders->with('finance')->whereHas('finance');
        } else {
            $orders->with('payment');
        }

        if ($reference !== null) {
            $orders->where('orders.reference', $reference);
        }
        $rows = $orders->orderByDesc('orders.created_at')->orderByDesc('orders.id')
            ->paginate(25, page: $reference === null ? null : 1);
        abort_if($reference !== null && $rows->isEmpty(), 404);

        $events = null;
        if ($settlement && $reference !== null) {
            $events = OrderWorkflowEvent::where('order_id', $rows->first()->id)
                ->whereIn('action', ['buyer_confirmed', 'delivery_issue_reported', 'delivery_issue_resolved', 'supervisor_approved', 'supervisor_hold', 'order_cancelled', 'case_opened', 'case_dismissed', 'case_refund_approved', 'case_refund_recorded', 'case_test_refund_recorded'])
                ->latest('id')->paginate(25, pageName: 'history_page');
        }

        return view('reports.ledger', compact('internal', 'settlement', 'scope', 'rows', 'events'));
    }
}
