<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Models\Branch;
use App\Models\Platform\Plan;
use App\Models\Platform\SubscriptionCallback;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionReminder;
use App\Notifications\SystemAlert;
use App\Services\Platform\SubscriptionService;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->plan = Plan::create(['name' => 'Business', 'slug' => 'business', 'price' => 60000, 'interval_months' => 1, 'max_branches' => 2, 'max_users' => 3, 'is_active' => true]);
    PlatformSettings::set(['fastlipa_enabled' => true, 'fastlipa_api_key' => 'platform-key', 'fastlipa_base_url' => 'https://api.fastlipa.test', 'grace_days' => 3]);

    $this->fl = ['status' => 'PENDING', 'amount' => 60000];
    Http::fake(function (HttpRequest $request) {
        if (str_contains($request->url(), '/v1/transaction/create')) {
            return Http::response(['status' => true, 'data' => ['tranID' => 'SUBTX'.strlen($request['reference']), 'payment_status' => 'PENDING']]);
        }
        if (str_contains($request->url(), '/v1/transaction/status')) {
            return Http::response(['status' => true, 'data' => ['tranid' => $request['tranid'], 'payment_status' => $this->fl['status'], 'amount' => $this->fl['amount']]]);
        }

        return null;
    });
});

function setAccess(array $attributes): Tenant
{
    $tenant = testTenant();
    $tenant->forceFill($attributes + ['trial_ends_at' => null, 'paid_until' => null, 'suspended_at' => null])->save();
    app(TenantManager::class)->initialize($tenant);

    return $tenant;
}

it('works out the subscription status, in PHP and in SQL', function () {
    $cases = [
        'trial' => ['trial_ends_at' => now()->addDays(5)],
        'active' => ['paid_until' => now()->addDays(20), 'trial_ends_at' => now()->subDays(40)],
        'grace' => ['paid_until' => now()->subDay()],
        'expired' => ['paid_until' => now()->subDays(10)],
        'suspended' => ['paid_until' => now()->addDays(20), 'suspended_at' => now()],
    ];
    foreach ($cases as $status => $attributes) {
        $tenant = setAccess($attributes);
        expect($tenant->status())->toBe($status, $status)
            ->and(Tenant::whereStatus($status)->whereKey($tenant->id)->exists())->toBeTrue($status);
    }
});

it('sends expired businesses to billing but lets them renew', function () {
    actingAsRole('owner');
    setAccess(['paid_until' => now()->subDays(10)]);

    $this->get('/dashboard')->assertRedirect(route('billing.index'));
    $this->getJson('/sales')->assertStatus(402)->assertJsonPath('billing_url', route('billing.index'));
    $this->get('/billing')->assertOk()->assertSee(__('Your subscription has expired.'));

    setAccess(['paid_until' => now()->subDay()]); // grace period
    $this->get('/dashboard')->assertOk()->assertSee(__('Your subscription has ended.'), false);
});

it('blocks sign-in for suspended businesses', function () {
    $owner = actingAsRole('owner');
    $owner->update(['email' => 'owner@first.test', 'password' => 'secret-pass-1']);
    setAccess(['paid_until' => now()->addMonth(), 'suspended_at' => now()]);
    auth()->logout();

    $this->post('/login', ['login' => 'owner@first.test', 'password' => 'secret-pass-1'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('extends the subscription only after FastLipa confirms the payment', function () {
    $owner = actingAsRole('owner');
    setAccess(['trial_ends_at' => now()->addDays(2)]);

    $this->post('/billing/pay', ['plan_id' => $this->plan->id, 'periods' => 2, 'phone' => '0754 112 233'])->assertSessionHas('success');
    $payment = SubscriptionPayment::sole();
    expect($payment->status)->toBe('processing')->and($payment->amount)->toBe('120000.00')->and($payment->months)->toBe(2)
        ->and($payment->phone)->toBe('255754112233')->and(testTenant()->paid_until)->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/transaction/create') && $r->hasHeader('Authorization', 'Bearer platform-key')
        && $r['amount'] === 120000 && $r['webhook_url'] === route('billing.callback'));

    // A double click does not send a second PIN prompt.
    $this->post('/billing/pay', ['plan_id' => $this->plan->id, 'periods' => 2, 'phone' => '0754112233']);
    expect(SubscriptionPayment::count())->toBe(1);

    // A webhook alone changes nothing while FastLipa still says pending.
    $webhook = ['event' => 'payment.completed', 'data' => ['tranID' => $payment->provider_reference, 'reference' => $payment->reference, 'amount' => '120000', 'status' => 'COMPLETED']];
    $this->postJson('/api/billing/callback/fastlipa', $webhook)->assertOk();
    expect($payment->fresh()->status)->toBe('processing');

    $this->fl = ['status' => 'COMPLETED', 'amount' => 120000];
    $this->postJson('/api/billing/callback/fastlipa', $webhook)->assertOk();
    $payment->refresh();
    $tenant = testTenant();
    expect($payment->status)->toBe('completed')->and($payment->number)->toStartWith('INV-SUB-')
        ->and($tenant->plan_id)->toBe($this->plan->id)->and($tenant->status())->toBe('active')
        ->and($tenant->paid_until->toDateString())->toBe(now()->startOfDay()->addMonthsNoOverflow(2)->toDateString());

    // Replays are harmless.
    $this->postJson('/api/billing/callback/fastlipa', $webhook)->assertOk();
    expect(testTenant()->paid_until->eq($tenant->paid_until))->toBeTrue()
        ->and(SubscriptionCallback::where('result', 'duplicate')->count())->toBe(1);

    $this->get(route('billing.status', $payment))->assertJsonPath('status', 'completed');
    $this->get(route('billing.invoice', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('never accepts less than the amount due and handles failed-then-completed', function () {
    actingAsRole('owner');
    setAccess(['trial_ends_at' => now()->addDay()]);
    $service = app(SubscriptionService::class);
    $payment = $service->startPush(testTenant(), $this->plan, 1, '0754112233');

    $this->fl = ['status' => 'COMPLETED', 'amount' => 1000];
    expect($service->refresh($payment)->status)->toBe('failed')->and(testTenant()->paid_until)->toBeNull();

    $late = $service->startPush(testTenant(), $this->plan, 1, '0754999888');
    $this->fl = ['status' => 'FAILED', 'amount' => 60000];
    expect($service->refresh($late)->status)->toBe('failed');
    $this->fl['status'] = 'COMPLETED';
    $this->artisan('billing:reconcile')->assertSuccessful();
    expect($late->fresh()->status)->toBe('completed')->and(testTenant()->status())->toBe('active');
});

it('rejects forged or foreign webhooks', function () {
    PlatformSettings::set(['fastlipa_webhook_secret' => 'whsec']);
    actingAsRole('owner');
    $payment = app(SubscriptionService::class)->startPush(testTenant(), $this->plan, 1, '0754112233');
    $this->fl['status'] = 'COMPLETED';

    $this->postJson('/api/billing/callback/fastlipa', ['data' => ['reference' => $payment->reference]], ['X-FastLipa-Signature' => 'forged'])->assertStatus(401);
    $this->postJson('/api/billing/callback/fastlipa', ['data' => ['reference' => 'SUB-UNKNOWN']])->assertOk();
    expect($payment->fresh()->status)->toBe('processing');
});

it('stacks manual payments, extensions and refunds on the paid-until date', function () {
    $service = app(SubscriptionService::class);
    setAccess(['paid_until' => now()->addDays(10)->endOfDay()]);
    $start = testTenant()->paid_until->copy();

    $payment = $service->recordManual(testTenant(), $this->plan, 3, '180000', 'bank', 'CRDB-889', null, null);
    expect($payment->status)->toBe('completed')->and($payment->period_start->toDateString())->toBe($start->toDateString())
        ->and(testTenant()->paid_until->toDateString())->toBe($start->copy()->addMonthsNoOverflow(3)->toDateString());

    $service->extend(testTenant(), 7);
    expect(testTenant()->paid_until->toDateString())->toBe($start->copy()->addMonthsNoOverflow(3)->addDays(7)->toDateString());

    $service->refund($payment, true, 'Duplicate transfer');
    expect($payment->fresh()->status)->toBe('refunded')
        ->and(testTenant()->paid_until->toDateString())->toBe($start->copy()->addMonthsNoOverflow(3)->addDays(7)->subMonthsNoOverflow(3)->toDateString());
});

it('enforces plan limits on branches and users', function () {
    $owner = actingAsRole('owner');
    testTenant()->update(['plan_id' => $this->plan->id]); // 2 branches, 3 users
    app(TenantManager::class)->initialize(testTenant()->fresh());

    $this->post('/branches', ['name' => 'Mbezi', 'code' => 'DSM02', 'is_active' => 1])->assertSessionHasNoErrors();
    $this->from('/branches/create')->post('/branches', ['name' => 'Arusha', 'code' => 'ARU01', 'is_active' => 1])
        ->assertRedirect('/branches/create')->assertSessionHas('error');
    expect(Branch::count())->toBe(2);

    User::factory()->count(2)->create();
    expect(fn () => User::factory()->create())->toThrow(BusinessRuleException::class);
    User::factory()->create(['is_active' => false]); // inactive users don't count
});

it('only lets owners and managers with settings access pay', function () {
    actingAsRole('cashier');
    $this->get('/billing')->assertOk()->assertDontSee(__('Pay with mobile money'));
    $this->post('/billing/pay', ['plan_id' => $this->plan->id, 'periods' => 1, 'phone' => '0754112233'])->assertForbidden();
});

it('reminds owners before the subscription ends, once', function () {
    Notification::fake();
    $owner = actingAsRole('owner');
    setAccess(['paid_until' => now()->addDays(3)->endOfDay()]);
    testTenant()->update(['owner_email' => 'owner@first.test']);

    $this->artisan('billing:reminders')->assertSuccessful();
    $this->artisan('billing:reminders')->assertSuccessful();

    Notification::assertSentTo($owner, SystemAlert::class);
    Notification::assertSentOnDemandTimes(SubscriptionReminder::class, 1);
    expect(tenant()->id)->toBe(1);
});

it('stops Livewire actions on an open page once the subscription has ended', function () {
    actingAsRole('owner');
    setAccess(['paid_until' => now()->subDays(10)]);

    expect(Livewire\Livewire::getPersistentMiddleware())->toContain(EnsureSubscriptionActive::class);
    $request = Request::create('/pos', 'POST', server: ['HTTP_X_LIVEWIRE' => '1', 'HTTP_ACCEPT' => 'application/json']);
    $request->setLaravelSession(app('session.store'));
    $response = app(EnsureSubscriptionActive::class)->handle($request, fn () => response('ran'));
    expect($response->isRedirect(route('billing.index')))->toBeTrue();
});
