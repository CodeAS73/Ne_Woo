<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\MerchantKyc;
use App\Models\ProductWidget;
use App\Models\Order;
use App\Services\SecurityAudit;
use App\Services\UserInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InternalController extends Controller
{
    public function dashboard()
    {
        $user = Auth::guard('internal')->user();
        return view('internal.dashboard', ['user' => $user, 'userCount' => $user->hasPermission('users.view') || $user->hasPermission('users.manage') ? User::count() : null, 'pendingKyc' => $user->hasPermission('kyc.review') ? MerchantKyc::where('status', 'pending')->count() : null]);
    }

    public function users()
    {
        return view('internal.users', ['users' => User::with('role')->latest()->paginate(20), 'roles' => Role::all()]);
    }

    public function createUser(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'], 'role' => ['required', Rule::in(array_keys(config('rbac.roles')))], 'fee_authority'=>['sometimes','boolean'], 'buyer_fee_authority'=>['sometimes','boolean']]);
        $data += $this->validateReportingPermissions($request);
        $url = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64)]);
            $user->role_id = Role::where('name', $data['role'])->sole()->id;
            $user->account_type = config('rbac.roles.'.$data['role'].'.type');
            $user->is_active = false;
            $user->can_manage_fee_tiers = $data['role'] === 'supervisor' && ($data['fee_authority'] ?? false);
            $user->can_manage_buyer_fees = $data['role'] === 'supervisor' && ($data['buyer_fee_authority'] ?? false);
            $user->reporting_permissions = $data['role'] === 'supervisor' ? ($data['reporting_permissions'] ?? []) : null;
            $user->save();
            SecurityAudit::record('admin.user_created', 'user', $user->id, ['role' => $data['role'], 'reporting_permissions' => $user->reporting_permissions]);
            return UserInvitations::create($user);
        });
        return back()->with('success', 'Profile created. Share this one-time password setup link privately; it expires in 24 hours.')->with('activation_url', $url);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(config('rbac.roles')))], 'is_active' => ['required', 'boolean'], 'fee_authority'=>['sometimes','boolean'], 'buyer_fee_authority'=>['sometimes','boolean']]);
        $data += $this->validateReportingPermissions($request);
        DB::transaction(function () use ($user, $data) {
            // Lock all admin rows in stable order to protect the last active administrator.
            $admins = User::whereHas('role', fn ($q) => $q->where('name', 'system_admin'))->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->isSystemAdmin() && ($data['role'] !== 'system_admin' || ! $data['is_active']) && $admins->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active System Administrator.']);
            }
            if ($data['is_active'] && \App\Models\UserActivation::where('user_id', $user->id)->whereNull('used_at')->exists()) {
                throw ValidationException::withMessages(['is_active' => 'This user must complete their password setup first.']);
            }
            $user->role_id = Role::where('name', $data['role'])->sole()->id;
            $user->account_type = config('rbac.roles.'.$data['role'].'.type');
            $user->is_active = $data['is_active'];
            $user->can_manage_fee_tiers = $data['role'] === 'supervisor' && (bool)($data['fee_authority'] ?? $user->can_manage_fee_tiers);
            $user->can_manage_buyer_fees = $data['role'] === 'supervisor' && (bool)($data['buyer_fee_authority'] ?? $user->can_manage_buyer_fees);
            $user->reporting_permissions = $data['role'] === 'supervisor'
                ? ($data['reporting_permissions'] ?? $user->reporting_permissions ?? [])
                : null;
            $user->session_version++;
            $user->save();
            if (! $user->is_active) {
                \App\Models\UserActivation::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            }
            SecurityAudit::record('admin.user_updated', 'user', $user->id, ['role' => $data['role'], 'active' => (bool) $data['is_active'], 'fee_authority'=>$user->can_manage_fee_tiers, 'buyer_fee_authority'=>$user->can_manage_buyer_fees, 'reporting_permissions' => $user->reporting_permissions]);
        });
        return back()->with('success', 'User access updated. Existing sessions have been revoked.');
    }

    private function validateReportingPermissions(Request $request): array
    {
        $data = $request->validate([
            'edit_reporting_permissions' => ['sometimes', 'boolean'],
            'reporting_permissions' => ['sometimes', 'array', 'list', 'max:3'],
            'reporting_permissions.*' => ['string', 'distinct', Rule::in(array_keys(config('rbac.supervisor_reporting')))],
        ]);

        if ($data['edit_reporting_permissions'] ?? false) {
            $data['reporting_permissions'] ??= [];
        }

        return $data;
    }

    public function kyc()
    {
        return view('internal.kyc', ['submissions' => MerchantKyc::with('user')->latest()->paginate(15)]);
    }

    public function products()
    {
        return view('internal.products', ['products' => ProductWidget::latest()->paginate(20), 'merchants' => User::where('account_type', 'merchant')->where('is_active', true)->get(['id', 'name', 'email'])]);
    }

    public function assignProduct(Request $request, ProductWidget $product)
    {
        $data = $request->validate(['merchant_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('account_type', 'merchant')->where('is_active', true)]]);
        $product->update(['merchant_user_id' => $data['merchant_user_id'], 'creator_hash' => null]);
        SecurityAudit::record('admin.product_assigned', 'product', $product->id, $data);
        return back()->with('success', 'Product ownership assigned.');
    }

    public function orders()
    {
        // No bulk buyer/merchant fee data is serialized to this operational overview.
        return view('internal.orders', ['orders' => Order::latest()->paginate(20)]);
    }

    public function orderDetail(Order $order, \App\Services\OrderPresenter $presenter)
    {
        return view('internal.order-detail', ['buyer' => $presenter->buyer($order), 'merchant' => $presenter->merchant($order)]);
    }

    public function invite(Request $request, User $user)
    {
        $url = DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->is_active, 409, 'Disable the profile before issuing a new password setup link.');
            $url = UserInvitations::create($user);
            SecurityAudit::record('admin.invitation_issued', 'user', $user->id);
            return $url;
        });
        return back()->with('success', 'A new one-time setup link is ready. All previous invitations for this account are invalid.')->with('activation_url', $url);
    }

    public function audits()
    {
        return view('internal.audits', ['events' => DB::table('security_audits')->latest('id')->paginate(30)]);
    }
}
