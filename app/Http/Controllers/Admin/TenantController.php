<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePlanRequest;
use App\Http\Requests\Admin\ExtendTenantRequest;
use App\Http\Requests\Admin\PurgeTenantRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\SuspendTenantRequest;
use App\Http\Requests\Admin\TenantStoreRequest;
use App\Http\Requests\Admin\TenantUpdateRequest;
use App\Models\Branch;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Platform\SubscriptionService;
use App\Support\Money;
use App\Support\PlatformSettings;
use App\Tenancy\TenantDatabase;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Platform admin: every business, its subscription, users and access.
 */
class TenantController extends Controller
{
    public function __construct(protected TenantManager $tenancy) {}

    public function index(): View
    {
        return view('admin.tenants.index');
    }

    public function create(): View
    {
        return view('admin.tenants.create', [
            'plans' => Plan::orderBy('sort_order')->pluck('name', 'id'),
            'trialDays' => (int) PlatformSettings::get('trial_days', 14),
            'defaultPlan' => PlatformSettings::get('default_plan_id'),
        ]);
    }

    public function store(TenantStoreRequest $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();
        $password = $data['password'] ?? Str::password(12, symbols: false);

        try {
            $tenant = $provisioner->provision([
                'business_name' => $data['business_name'],
                'owner_name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $password,
                'plan_id' => $data['plan_id'] ?? null,
                'trial_days' => $data['trial_days'],
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Admin provisioning failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', __('The business could not be created: :error', ['error' => Str::limit($e->getMessage(), 200)]));
        }

        AdminActivity::record('tenant.created', "Created business {$tenant->name}", $tenant);
        $flash = redirect()->route('admin.tenants.show', $tenant)->with('success', __('Business created.'));

        return empty($data['password']) ? $flash->with('generated_password', ['email' => $data['email'], 'password' => $password]) : $flash;
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load('plan');
        [$usage, $users, $error] = $this->insideTenant($tenant);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'status' => $tenant->trashed() ? 'deleted' : $tenant->status(),
            'usage' => $usage,
            'users' => $users,
            'usageError' => $error,
            'plans' => Plan::orderBy('sort_order')->get(),
            'totalPaid' => Money::round($tenant->payments()->where('status', 'completed')->sum('amount')),
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.edit', ['tenant' => $tenant, 'plans' => Plan::orderBy('sort_order')->pluck('name', 'id')]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();
        foreach (['trial_ends_at', 'paid_until'] as $date) {
            $data[$date] = ! empty($data[$date]) ? Carbon::parse($data[$date])->endOfDay() : null;
        }
        $tenant->fill($data);
        $changes = $tenant->getDirty();
        $tenant->save();
        AdminActivity::record('tenant.updated', "Updated business {$tenant->name}", $tenant, ['changed' => array_keys($changes)]);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', __('Business updated.'));
    }

    public function extend(ExtendTenantRequest $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $days = (int) $request->validated('days');
        $tenant = $subscriptions->extend($tenant, $days);
        AdminActivity::record('tenant.extended', "Extended {$tenant->name} by {$days} day(s)", $tenant, ['days' => $days, 'reason' => $request->validated('reason'), 'until' => $tenant->accessEndsAt()?->toDateString()]);

        return back()->with('success', __('Access extended to :date.', ['date' => $tenant->accessEndsAt()->format('d/m/Y')]));
    }

    public function changePlan(ChangePlanRequest $request, Tenant $tenant): RedirectResponse
    {
        $old = $tenant->plan?->name;
        $tenant->update(['plan_id' => $request->validated('plan_id')]);
        AdminActivity::record('tenant.plan', "Changed plan of {$tenant->name}", $tenant, ['from' => $old, 'to' => $tenant->fresh('plan')->plan?->name]);

        return back()->with('success', __('Plan changed.'));
    }

    public function recordPayment(RecordPaymentRequest $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validated();
        $payment = $subscriptions->recordManual(
            $tenant, Plan::findOrFail($data['plan_id']), (int) $data['periods'], (string) $data['amount'], $data['method'],
            $data['reference'] ?? null, $data['note'] ?? null, $request->user('admin'),
            ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
        );
        AdminActivity::record('payment.recorded', "Recorded {$payment->method} payment ".money($payment->amount)." for {$tenant->name}", $tenant, ['payment' => $payment->number]);

        return back()->with('success', __('Payment recorded. Paid until :date.', ['date' => $payment->period_end->format('d/m/Y')]));
    }

    public function suspend(SuspendTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update(['suspended_at' => now(), 'suspension_reason' => $request->validated('reason')]);
        AdminActivity::record('tenant.suspended', "Suspended {$tenant->name}", $tenant, ['reason' => $request->validated('reason')]);

        return back()->with('success', __('Business suspended. Its users can no longer sign in.'));
    }

    public function unsuspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['suspended_at' => null, 'suspension_reason' => null]);
        AdminActivity::record('tenant.unsuspended', "Restored access for {$tenant->name}", $tenant);

        return back()->with('success', __('Business restored.'));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();
        AdminActivity::record('tenant.deleted', "Deleted business {$tenant->name}", $tenant);

        return redirect()->route('admin.tenants.index')->with('success', __('Business deleted. Its data is kept and it can be restored.'));
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        $tenant->restore();
        AdminActivity::record('tenant.restored', "Restored business {$tenant->name}", $tenant);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', __('Business restored.'));
    }

    /** Permanently remove a deleted business: its database and files. */
    public function purge(PurgeTenantRequest $request, Tenant $tenant, TenantDatabase $databases): RedirectResponse
    {
        abort_unless($tenant->trashed(), 422, __('Delete the business first.'));

        $name = $tenant->name;
        try {
            $databases->drop($tenant);
        } catch (Throwable $e) {
            return back()->with('error', __('Could not drop the database: :error', ['error' => Str::limit($e->getMessage(), 200)]));
        }
        if ($tenant->storage_folder) {
            File::deleteDirectory(storage_path('app/private/'.$tenant->storage_folder));
            File::deleteDirectory(storage_path('app/public/'.$tenant->storage_folder));
        }
        AdminActivity::record('tenant.purged', "Permanently deleted business {$name}", null, ['tenant_id' => $tenant->id, 'database' => $tenant->database]);
        $tenant->logins()->delete();
        $tenant->forceDelete();

        return redirect()->route('admin.tenants.index')->with('success', __(':name and all its data were permanently deleted.', ['name' => $name]));
    }

    /** Open the business as its owner, for support. */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_if($tenant->trashed(), 404);
        $owner = $this->tenancy->run($tenant, function () {
            $owner = User::role('owner')->where('is_active', true)->orderBy('id')->first();
            if ($owner) {
                activity('auth')->causedBy($owner)->performedOn($owner)
                    ->withProperties(['admin' => auth('admin')->user()->email])->log('Platform admin signed in as this user');
            }

            return $owner;
        });
        if (! $owner) {
            return back()->with('error', __('This business has no active owner account.'));
        }

        AdminActivity::record('tenant.impersonated', "Signed in to {$tenant->name} as {$owner->name}", $tenant, ['user_id' => $owner->id]);
        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->put('admin_impersonator_id', auth('admin')->id());
        $request->session()->forget('impersonator_id');
        $this->tenancy->run($tenant, fn () => Auth::guard('web')->login($owner));

        return redirect()->route('dashboard');
    }

    public function resetUserPassword(Tenant $tenant, int $user): RedirectResponse
    {
        $password = Str::password(12, symbols: false);
        $account = $this->tenancy->run($tenant, function () use ($user, $password) {
            $account = User::findOrFail($user);
            $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            activity('auth')->performedOn($account)->withProperties(['admin' => auth('admin')->user()->email])->log('Password reset by platform admin');

            return $account;
        });
        AdminActivity::record('user.password', "Reset password for {$account->email} at {$tenant->name}", $tenant, ['user_id' => $user]);

        return back()->with('success', __('Password reset.'))->with('generated_password', ['email' => $account->email ?? $account->phone, 'password' => $password]);
    }

    public function toggleUser(Tenant $tenant, int $user): RedirectResponse
    {
        $account = $this->tenancy->run($tenant, function () use ($user) {
            $account = User::findOrFail($user);
            if ($account->is_active && $account->hasRole('owner') && User::role('owner')->where('is_active', true)->count() <= 1) {
                return null;
            }
            $account->update(['is_active' => ! $account->is_active]);

            return $account;
        });
        if (! $account) {
            return back()->with('error', __('This is the only active owner. Add another owner before deactivating this one.'));
        }
        AdminActivity::record('user.toggled', ($account->is_active ? 'Activated ' : 'Deactivated ').$account->email." at {$tenant->name}", $tenant, ['user_id' => $user]);

        return back()->with('success', $account->is_active ? __('User activated.') : __('User deactivated.'));
    }

    /** Usage figures and users, read from the business's own database. */
    protected function insideTenant(Tenant $tenant): array
    {
        if (! $tenant->provisioned_at) {
            return [null, collect(), __('This business was not fully set up.')];
        }

        try {
            return $this->tenancy->run($tenant, fn () => [
                [
                    'branches' => Branch::withoutGlobalScopes()->count(),
                    'users' => User::where('is_active', true)->count(),
                    'products' => Product::withoutGlobalScopes()->whereNull('parent_id')->count(),
                    'sales_30' => Sale::withoutGlobalScopes()->where('status', 'completed')->where('created_at', '>=', now()->subDays(30))->count(),
                    'revenue_30' => Money::round(Sale::withoutGlobalScopes()->where('status', 'completed')->where('created_at', '>=', now()->subDays(30))->sum('total')),
                    'last_sale' => Sale::withoutGlobalScopes()->where('status', 'completed')->max('created_at'),
                ],
                User::withTrashed()->with('roles')->orderByDesc('is_active')->orderBy('name')->get(),
                null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not read business database', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return [null, collect(), __('The business database could not be read: :error', ['error' => Str::limit($e->getMessage(), 160)])];
        }
    }
}
