<?php

use App\Models\Branch;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Tenancy\TenantDatabase;
use App\Tenancy\TenantManager;

beforeEach(function () {
    app(TenantManager::class)->end(); // a visitor, not inside any business
    $this->plan = Plan::create(['name' => 'Starter', 'slug' => 'starter', 'price' => 25000, 'interval_months' => 1, 'max_branches' => 1, 'is_active' => true]);
    PlatformSettings::set(['signups_enabled' => true, 'trial_days' => 14]);
});

function signupData(array $overrides = []): array
{
    return $overrides + [
        'business_name' => 'Mama Neema Supermarket',
        'owner_name' => 'Neema Mushi',
        'email' => 'Neema@Shop.test',
        'phone' => '0712 345 678',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'plan_id' => test()->plan->id,
        'terms' => '1',
    ];
}

it('shows the sign-up page and links it from the login page', function () {
    $this->get('/register')->assertOk()->assertSee('Starter')->assertSee(__('Create account'));
    $this->get('/login')->assertSee(route('register'));
});

it('creates a business with its own database and signs the owner in', function () {
    $this->post('/register', signupData())->assertRedirect(route('dashboard'));

    $tenant = Tenant::where('owner_email', 'neema@shop.test')->sole();
    $this->beforeApplicationDestroyed(fn () => app(TenantDatabase::class)->drop($tenant));
    expect($tenant->name)->toBe('Mama Neema Supermarket')->and($tenant->status())->toBe('trial')
        ->and($tenant->plan_id)->toBe($this->plan->id)->and($tenant->daysLeft())->toBe(14)
        ->and($tenant->provisioned_at)->not->toBeNull()
        ->and(session('tenant_id'))->toBe($tenant->id);
    $this->assertAuthenticated();

    inTenant($tenant, function () {
        $owner = User::sole();
        expect($owner->email)->toBe('neema@shop.test')->and($owner->phone)->toBe('255712345678')
            ->and($owner->hasRole('owner'))->toBeTrue()->and(Branch::count())->toBe(1)
            ->and(setting('business.name'))->toBe('Mama Neema Supermarket');
    });

    $this->get('/dashboard')->assertOk()->assertSee('Mama Neema Supermarket');
});

it('refuses an email that already has an account', function () {
    inTenant(testTenant(), fn () => User::factory()->create(['email' => 'neema@shop.test']));
    $this->post('/register', signupData())->assertSessionHasErrors('email');
    expect(Tenant::count())->toBe(1);
});

it('validates the form and respects the sign-up switch', function () {
    $this->post('/register', signupData(['phone' => '12345', 'terms' => null, 'password_confirmation' => 'nope']))
        ->assertSessionHasErrors(['phone', 'terms', 'password']);

    PlatformSettings::set(['signups_enabled' => false]);
    $this->get('/register')->assertNotFound();
    $this->post('/register', signupData())->assertForbidden();
});
