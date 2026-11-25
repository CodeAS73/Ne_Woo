<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MerchantOrderFinance;
use App\Models\OrderWorkflowEvent;
use App\Services\AccountAccess;
use App\Services\CheckoutAccess;
use App\Services\DeliveryWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeliveryWorkflowController extends Controller
{
    public function merchant(Request $request, Order $order, DeliveryWorkflow $workflow)
    {
        $user = AccountAccess::user();
        abort_unless($user && $workflow->merchantOwns($user, $order), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['preparing', 'dispatched', 'ready_for_pickup', 'delivered'])], 'note' => ['nullable', 'string', 'max:500']]);
        $workflow->updateMerchant($order, $user, $data['status'], $data['note'] ?? null);
        return back()->with('success', 'Fulfilment update saved. Buyer confirmation is required before settlement review.');
    }

    public function confirm(Request $request, Order $order, DeliveryWorkflow $workflow)
    {
        abort_unless(CheckoutAccess::owns($request, $order), 404);
        $request->validate(['received' => ['accepted']]);
        $workflow->confirmBuyer($order, AccountAccess::user());
        return redirect()->route('orders.show', $order)->with('success', 'Receipt confirmed. The order is awaiting supervisor review; no funds have been released.');
    }

    public function issue(Request $request, Order $order, DeliveryWorkflow $workflow)
    {
        abort_unless(CheckoutAccess::owns($request, $order), 404);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);
        $workflow->reportIssue($order, AccountAccess::user(), $data['note']);
        return redirect()->route('orders.show', $order)->with('success', 'Your delivery issue is recorded. Settlement is on hold for internal review.');
    }

    public function queue(Request $request, DeliveryWorkflow $workflow)
    {
        $filter = $request->validate(['filter' => ['nullable', Rule::in(['pending', 'on_hold', 'approved', 'all'])]])['filter'] ?? 'pending';
        $orders = Order::whereHas('finance', fn ($query) => $filter === 'all' ? $query->where('review_status', '!=', 'not_ready') : $query->where('review_status', $filter))->orderBy('buyer_confirmed_at')->paginate(15)->withQueryString();
        $rows = $orders->getCollection()->map(function ($order) use ($workflow) {
            $finance = $order->finance;
            return ['reference' => $order->reference, 'name' => $order->product_name, 'method' => $order->fulfillment_method, 'workflow' => $workflow->presentation($order), 'review_note' => $finance->review_note,
                'events' => OrderWorkflowEvent::where('order_id', $order->id)->where('visibility', 'internal')->orderBy('id')->get()->map(fn ($event) => ['action' => $event->action, 'note' => $event->note, 'at' => $event->created_at->timezone('Africa/Nairobi')->format('d M Y, H:i').' EAT'])->all()];
        });
        return view('internal.delivery-reviews', ['rows' => $rows, 'pagination' => $orders->links(), 'filter' => $filter]);
    }

    public function review(Request $request, Order $order, DeliveryWorkflow $workflow)
    {
        abort_if(CheckoutAccess::owns($request, $order), 403, 'A different reviewer must review their own order.');
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'hold', 'resolve_issue'])], 'note' => ['required', 'string', 'min:5', 'max:1000']]);
        $workflow->review($order, Auth::guard('internal')->user(), $data['decision'], $data['note']);
        return back()->with('success', 'Review saved. Approval does not transfer money; payout scheduling remains disabled.');
    }
}
