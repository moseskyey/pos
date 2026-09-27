<?php

use App\Models\Branch;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\Announcement;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\Platform\SubscriptionService;
use App\Support\PlatformSettings;
use App\Tenancy\TenantDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

beforeEach(function () {
    $this->plan = Plan::create(['name' => 'Business', 'slug' => 'business', 'price' => 60000, 'interval_months' => 1, 'is_active' => true]);
});

it('keeps shop users and guests out of the admin area', function () {
    $this->get('/admin')->assertRedirect(route('admin.login'));
    actingAsRole('owner');
    $this->get('/admin')->assertRedirect(route('admin.login'));
    $this->get('/admin/login')->assertOk();
});

it('signs admins in and out, and throttles guessing', function () {
    PlatformAdmin::create(['name' => 'Neema', 'email' => 'neema@platform.test', 'password' => 'admin-password', 'is_super' => true]);

    $this->post('/admin/login', ['email' => 'neema@platform.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/admin/login', ['email' => 'NEEMA@platform.test', 'password' => 'admin-password'])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('admin');
    $this->get('/admin')->assertOk()->assertSee(__('Platform overview'));
    $this->post('/admin/logout')->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');

    PlatformAdmin::where('email', 'neema@platform.test')->update(['is_active' => false]);
    $this->post('/admin/login', ['email' => 'neema@platform.test', 'password' => 'admin-password'])->assertSessionHasErrors('email');
});

it('keeps settings and admins for super admins', function () {
    actingAsAdmin(super: false);
    $this->get('/admin/settings')->assertForbidden();
    $this->get('/admin/admins')->assertForbidden();
    $this->get('/admin/businesses')->assertOk();
});

it('renders every admin page', function () {
    $admin = actingAsAdmin();
    $tenant = testTenant();
    $tenant->update(['plan_id' => $this->plan->id]);
    $payment = app(SubscriptionService::class)->recordManual($tenant, $this->plan, 1, '60000', 'cash', null, null, $admin);
    Announcement::create(['title' => 'Maintenance tonight', 'level' => 'warning', 'is_active' => true]);
    AdminActivity::record('test', 'Test entry', $tenant);

    $ids = ['tenant' => $tenant->id, 'plan' => $this->plan->id, 'announcement' => Announcement::value('id'), 'admin' => $admin->id, 'payment' => $payment->id];
    $urls = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $r) => in_array('GET', $r->methods()) && str_starts_with((string) $r->getName(), 'admin.') && $r->getName() !== 'admin.login')
        ->map(fn (Route $r) => '/'.preg_replace_callback('/\{(\w+)\}/', fn ($m) => $ids[$m[1]], $r->uri()));

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
    expect($urls->count())->toBeGreaterThan(12);
    $this->get('/admin/businesses/'.$tenant->id)->assertSee('Test Business')->assertSee($payment->number);
});

it('creates a business, then extends, bills, suspends and restores it', function () {
    actingAsAdmin();
    $this->post('/admin/businesses', ['business_name' => 'Arusha Pharmacy', 'owner_name' => 'Dr. Baraka', 'email' => 'baraka@pharmacy.test', 'phone' => '0765 111 222', 'plan_id' => $this->plan->id, 'trial_days' => 7])
        ->assertSessionHas('generated_password');
    $tenant = Tenant::where('name', 'Arusha Pharmacy')->sole();
    $this->beforeApplicationDestroyed(fn () => app(TenantDatabase::class)->drop($tenant));
    expect($tenant->status())->toBe('trial')->and($tenant->daysLeft())->toBe(7);

    $this->post(route('admin.tenants.extend', $tenant), ['days' => 10, 'reason' => 'Onboarding'])->assertSessionHas('success');
    expect($tenant->fresh()->daysLeft())->toBe(17);

    $this->post(route('admin.tenants.payments.store', $tenant), ['plan_id' => $this->plan->id, 'periods' => 12, 'amount' => 600000, 'method' => 'bank', 'reference' => 'NMB-7788'])->assertSessionHas('success');
    $tenant->refresh();
    expect($tenant->status())->toBe('active')->and(SubscriptionPayment::where('tenant_id', $tenant->id)->sole()->recorded_by)->not->toBeNull()
        ->and($tenant->paid_until->toDateString())->toBe(now()->startOfDay()->addMonthsNoOverflow(12)->toDateString());

    $this->post(route('admin.tenants.suspend', $tenant), ['reason' => 'Unpaid bank charge'])->assertSessionHas('success');
    expect($tenant->fresh()->status())->toBe('suspended');
    $this->post(route('admin.tenants.unsuspend', $tenant));
    expect($tenant->fresh()->status())->toBe('active');

    $this->post(route('admin.tenants.plan', $tenant), ['plan_id' => ''])->assertSessionHas('success');
    expect($tenant->fresh()->plan_id)->toBeNull();

    expect(AdminActivity::where('tenant_id', $tenant->id)->pluck('action')->all())
        ->toContain('tenant.created', 'tenant.extended', 'payment.recorded', 'tenant.suspended', 'tenant.unsuspended', 'tenant.plan');
});

it('opens a business as its owner and comes back', function () {
    $owner = inTenant(testTenant(), function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['default_branch_id' => $branch->id]);
        $owner->assignRole('owner');
        $owner->branches()->attach($branch);

        return $owner;
    });
    testTenant()->update(['paid_until' => now()->subMonth()]); // expired: admins still get in

    actingAsAdmin();
    $this->post(route('admin.tenants.impersonate', 1))->assertRedirect(route('dashboard'));
    expect(session('admin_impersonator_id'))->not->toBeNull();
    $this->get('/dashboard')->assertOk()->assertSee(__('Back to admin'));
    expect(auth('web')->id())->toBe($owner->id);

    $this->post(route('impersonate.admin.leave'))->assertRedirect(route('admin.tenants.show', 1));
    $this->assertGuest('web');
    $this->assertAuthenticated('admin');
});

it('resets passwords and deactivates users inside a business', function () {
    actingAsAdmin();
    [$owner, $cashier] = inTenant(testTenant(), function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create(['email' => 'owner@first.test']);
        $owner->assignRole('owner');
        $cashier = User::factory()->create(['email' => 'cashier@first.test']);
        $cashier->assignRole('cashier');

        return [$owner, $cashier];
    });

    $response = $this->post(route('admin.tenants.users.password', [1, $cashier->id]))->assertSessionHas('generated_password');
    $password = session('generated_password')['password'];
    expect(inTenant(testTenant(), fn () => Hash::check($password, User::find($cashier->id)->password)))->toBeTrue();

    $this->post(route('admin.tenants.users.toggle', [1, $cashier->id]))->assertSessionHas('success');
    expect(inTenant(testTenant(), fn () => User::find($cashier->id)->is_active))->toBeFalse();

    // The last active owner stays active.
    $this->post(route('admin.tenants.users.toggle', [1, $owner->id]))->assertSessionHas('error');
});

it('manages plans, announcements and platform settings', function () {
    actingAsAdmin();
    $this->post('/admin/plans', ['name' => 'Duka Pro', 'price' => 45000, 'interval_months' => 3, 'max_branches' => 2, 'max_users' => '', 'is_active' => 1])->assertRedirect(route('admin.plans.index'));
    $plan = Plan::where('slug', 'duka-pro')->sole();
    expect($plan->max_users)->toBeNull()->and($plan->monthlyPrice())->toBe('15000.00');

    testTenant()->update(['plan_id' => $plan->id]);
    $this->delete(route('admin.plans.destroy', $plan));
    expect($plan->fresh()->is_active)->toBeFalse(); // in use: deactivated, not deleted

    $this->post('/admin/announcements', ['title' => 'New M-Pesa rates', 'body' => 'From Monday.', 'level' => 'info', 'is_active' => 1])->assertRedirect();
    auth('admin')->logout();
    actingAsRole('owner');
    $this->get('/dashboard')->assertSee('New M-Pesa rates');

    actingAsAdmin();
    PlatformSettings::set(['fastlipa_api_key' => 'saved-key']);
    $this->put('/admin/settings', ['name' => 'DukaPOS Cloud', 'trial_days' => 30, 'grace_days' => 5, 'fastlipa_base_url' => 'https://api.fastlipa.com', 'fastlipa_api_key' => '', 'signups_enabled' => 1])->assertSessionHas('success');
    expect(PlatformSettings::get('trial_days'))->toBe(30)->and(PlatformSettings::get('fastlipa_api_key'))->toBe('saved-key')
        ->and(PlatformSettings::get('name'))->toBe('DukaPOS Cloud');
});

it('keeps at least one super admin', function () {
    $me = actingAsAdmin();
    $other = PlatformAdmin::create(['name' => 'Support', 'email' => 'help@platform.test', 'password' => 'admin-password', 'is_super' => false]);
    $this->delete(route('admin.admins.destroy', $me))->assertSessionHas('error');
    $this->put(route('admin.admins.update', $me), ['name' => 'Me', 'email' => $me->email, 'is_super' => 0, 'is_active' => 0]);
    expect($me->fresh()->is_super)->toBeTrue()->and($me->fresh()->is_active)->toBeTrue();
    $this->delete(route('admin.admins.destroy', $other))->assertSessionHas('success');
});

it('deletes, restores and purges a business', function () {
    actingAsAdmin();
    $second = provisionTenant(['email' => 'juma@second.test', 'password' => 'secret-pass-1']);

    $this->delete(route('admin.tenants.destroy', $second))->assertRedirect(route('admin.tenants.index'));
    auth('admin')->logout();
    $this->post('/login', ['login' => 'juma@second.test', 'password' => 'secret-pass-1'])->assertSessionHasErrors('login');

    actingAsAdmin();
    $this->post(route('admin.tenants.restore', $second));
    expect($second->fresh()->trashed())->toBeFalse();

    $second->delete();
    $this->delete(route('admin.tenants.purge', $second), ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
    $this->delete(route('admin.tenants.purge', $second), ['confirm' => $second->slug])->assertRedirect(route('admin.tenants.index'));
    expect(Tenant::withTrashed()->find($second->id))->toBeNull()
        ->and(app(TenantDatabase::class)->exists($second))->toBeFalse();
});
