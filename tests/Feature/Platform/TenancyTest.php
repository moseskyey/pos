<?php

use App\Models\Branch;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantLogin;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('runs the test suite inside business #1 on its own connection', function () {
    expect(tenant()->id)->toBe(1)
        ->and(DB::getDefaultConnection())->toBe('tenant_1')
        ->and((new Tenant)->getConnectionName())->toBe('central');
});

it('keeps every business in its own database', function () {
    actingAsRole('owner');
    Product::factory()->create(['name' => 'Azam Maji 500ml']);
    setting()->set('business.name', 'Kariakoo Mini-Mart');

    $second = provisionTenant(['business_name' => 'Mbezi Hardware']);

    inTenant($second, function () {
        expect(Product::count())->toBe(0)
            ->and(setting('business.name'))->toBe('Mbezi Hardware')
            ->and(Branch::count())->toBe(1)
            ->and(User::first()->hasRole('owner'))->toBeTrue();
        Product::factory()->create(['name' => 'Cement 50kg']);
    });

    expect(tenant()->id)->toBe(1)
        ->and(Product::pluck('name')->all())->toBe(['Azam Maji 500ml'])
        ->and(setting('business.name'))->toBe('Kariakoo Mini-Mart');
    expect(inTenant($second, fn () => Product::pluck('name')->all()))->toBe(['Cement 50kg']);
});

it('keeps files apart per business', function () {
    $second = provisionTenant();
    $root1 = config('filesystems.disks.local.root');
    $root2 = inTenant($second, fn () => config('filesystems.disks.local.root'));

    expect($root2)->toEndWith('tenants/'.$second->id)->and($root2)->not->toBe($root1)
        ->and(inTenant($second, fn () => config('backup.backup.name')))->toBe('dukapos-'.$second->id)
        ->and(inTenant($second, fn () => config('backup.backup.source.databases')))->toBe(['tenant_'.$second->id]);
    inTenant($second, fn () => Storage::disk('local')->put('x.txt', 'second'));
    expect(Storage::disk('local')->exists('x.txt'))->toBeFalse();
    inTenant($second, fn () => Storage::disk('local')->delete('x.txt'));
});

it('indexes users centrally and keeps emails unique across businesses', function () {
    $second = provisionTenant(['email' => 'juma@second.test', 'phone' => '255754000111']);
    $login = TenantLogin::where('email', 'juma@second.test')->first();
    expect($login->tenant_id)->toBe($second->id)->and($login->phone)->toBe('255754000111');

    expect(fn () => User::factory()->create(['email' => 'Juma@Second.test']))->toThrow(ValidationException::class);
    expect(fn () => User::factory()->create(['phone' => '0754 000 111']))->toThrow(ValidationException::class);

    $user = User::factory()->create(['email' => 'mary@first.test']);
    $user->update(['name' => 'Mary Kweka', 'is_active' => false]);
    $entry = TenantLogin::where('email', 'mary@first.test')->first();
    expect($entry->tenant_id)->toBe(1)->and($entry->name)->toBe('Mary Kweka')->and($entry->is_active)->toBeFalse();
});

it('signs users in to the business their email belongs to', function () {
    $second = provisionTenant(['business_name' => 'Mbezi Hardware', 'email' => 'juma@second.test', 'password' => 'secret-pass-1']);
    app(TenantManager::class)->end();

    $this->post('/login', ['login' => 'juma@second.test', 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
    expect(session('tenant_id'))->toBe($second->id)->and(tenant()->id)->toBe($second->id);
    $this->get('/dashboard')->assertOk()->assertSee('Mbezi Hardware');

    // Phone works too.
    $this->post('/logout');
    $this->post('/login', ['login' => '0754 000 111', 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
    expect(session('tenant_id'))->toBe($second->id);
});

it('rejects unknown and wrong credentials without revealing which', function () {
    provisionTenant(['email' => 'juma@second.test', 'password' => 'secret-pass-1']);
    $this->post('/login', ['login' => 'nobody@nowhere.test', 'password' => 'x'])->assertSessionHasErrors('login');
    $this->post('/login', ['login' => 'juma@second.test', 'password' => 'wrong-pass'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('sends password reset links for the right business', function () {
    Notification::fake();
    provisionTenant(['email' => 'juma@second.test']);
    $this->post('/forgot-password', ['email' => 'juma@second.test'])->assertSessionHas('status');
    Notification::assertCount(1);
    $this->post('/forgot-password', ['email' => 'nobody@nowhere.test'])->assertSessionHasErrors('email');
});

it('carries the business through queued jobs', function () {
    $second = provisionTenant();
    inTenant($second, function () {
        dispatch(fn () => app()->instance('seen', [tenant()?->id, DB::getDefaultConnection()]));
    });

    expect(app('seen'))->toBe([$second->id, 'tenant_'.$second->id])->and(tenant()->id)->toBe(1);
});

it('puts the business in public links and resolves them', function () {
    $owner = actingAsRole('owner');
    $sale = Sale::withoutGlobalScopes()->forceCreate(['branch_id' => Branch::first()->id, 'user_id' => $owner->id, 'number' => 'INV-DSM01-000123', 'status' => 'completed', 'total' => 5000]);

    $url = $sale->verificationUrl();
    expect($url)->toContain('/t/1/verify/INV-DSM01-000123');
    auth()->logout();
    $this->get($url)->assertOk()->assertSee('INV-DSM01-000123');

    // A number from another business is not found through this business's link.
    $second = provisionTenant();
    $foreign = str_replace('/t/1/', '/t/'.$second->id.'/', $url);
    $this->get($foreign)->assertForbidden(); // signature covers the business
});

it('routes legacy links and webhooks to the adopted business', function () {
    $this->postJson('/api/payments/callback/fastlipa', ['event' => 'payment.completed'])->assertNotFound();
    PlatformSettings::set(['legacy_tenant_id' => 1]);
    setting()->set('payments.gateway', 'fastlipa');
    $this->postJson('/api/payments/callback/fastlipa', ['event' => 'payment.completed'])->assertOk();
});

it('migrates and runs commands for every business', function () {
    $second = provisionTenant();
    $this->artisan('tenants:migrate')->assertSuccessful();
    $this->artisan('tenants:run', ['commandline' => 'dukapos:stock-alerts', '--tenant' => [$second->id]])->assertSuccessful();
    // Business commands refuse to run without a business.
    app(TenantManager::class)->end();
    $this->artisan('dukapos:stock-alerts')->assertFailed();
});
