<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderWorkflowEvent;
use App\Services\CheckoutAccess;
use App\Services\OrderOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrderOperationsController extends Controller
{
    public function show(Request $request, Order $order)
    {
        [$actor, $side] = $this->access($request, $order);
        $case = $request->integer('case');
        $cases = OrderCase::where('order_id', $order->id)->latest('id');
        $selected = $case ? (clone $cases)->findOrFail($case) : (clone $cases)->first();
        $events = $selected ? OrderWorkflowEvent::where('order_id', $order->id)
            ->where('visibility', 'case:'.$selected->id)->latest('id')->paginate(20)->withQueryString() : null;
        $activeCase = (clone $cases)->whereIn('status', ['open', 'refund_approved'])->exists();
        $cases = $cases->paginate(15, pageName: 'cases_page')->withQueryString();
        $canCancel = $order->status === 'awaiting_payment' && $order->payment_status === 'unpaid'
            && in_array($order->payment?->status, ['not_initiated', 'test_failed'], true);
        $canOpen = ! $activeCase && app(\App\Services\DeliveryWorkflow::class)->funded($order);

        return response()->view('order-operations', compact('order', 'actor', 'side', 'cases', 'selected', 'events', 'canCancel', 'canOpen'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function submit(Request $request, Order $order, OrderOperations $operations)
    {
        [$actor, $side] = $this->access($request, $order);
        $data = $request->validate([
            'action' => ['required', Rule::in(['cancel', 'cancellation', 'refund', 'dispute', 'evidence', 'decide'])],
            'note' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        if ($data['action'] === 'cancel') {
            $operations->cancel($order, $actor, $side, $data['note']);
            return back()->with('success', 'Order cancelled.');
        }
        if (in_array($data['action'], ['cancellation', 'refund', 'dispute'], true)) {
            $case = $operations->open($order, $actor, $side, $data['action'], $data['note']);
            return redirect()->route($request->route()->getName() === 'internal.order-operations.submit'
                ? 'internal.order-operations' : 'orders.operations', [$order, 'case' => $case->id])->with('success', 'Case opened.');
        }
        $caseId = $request->validate(['case_id' => ['required', 'integer']])['case_id'];
        $case = OrderCase::where('order_id', $order->id)->findOrFail($caseId);
        if ($data['action'] === 'evidence') {
            $operations->evidence($case, $actor, $side, $data['note']);
        } else {
            abort_unless($side === 'staff' && ! CheckoutAccess::owns($request, $order), 403);
            $data += $request->validate([
                'decision' => ['required', Rule::in(['dismiss', 'approve_refund', 'record_refund'])],
                'refund_minor' => ['nullable', 'integer', 'min:1'],
                'restock_quantity' => ['nullable', 'integer', 'min:0'],
                'reference' => ['nullable', 'string', 'max:100'],
            ]);
            if (isset($data['refund_minor'])) {
                $data['refund_minor'] = (int) $data['refund_minor'];
            }
            $data['restock_quantity'] = (int) ($data['restock_quantity'] ?? 0);
            $operations->decide($case, $actor, $data);
        }

        return back()->with('success', 'Case updated.');
    }

    public function queue()
    {
        $cases = OrderCase::with('order')->latest('id')->paginate(25);

        return view('internal.order-cases', compact('cases'));
    }

    private function access(Request $request, Order $order): array
    {
        if ($request->routeIs('internal.*')) {
            return [Auth::guard('internal')->user(), 'staff'];
        }
        if (CheckoutAccess::owns($request, $order)) {
            return [Auth::guard('web')->user(), 'buyer'];
        }

        $merchant = Auth::guard('web')->user();
        abort_unless($merchant?->hasPermission('orders.fulfill_own') && $order->merchant_user_id === $merchant->id, 404);

        return [$merchant, 'merchant'];
    }
}
