<?php

use App\Contracts\SmsGateway;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\PaymentCallback;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\Register;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    setting()->set(['payments.gateway' => 'fastlipa', 'payments.fastlipa_api_key' => 'test-key', 'payments.fastlipa_base_url' => 'https://fastlipa.test', 'payments.fastlipa_webhook_secret' => 'whsec']);
    $this->payments = app(PaymentService::class);
});

function initiateIntent(array $response = ['status' => 'success', 'data' => ['tranID' => 'FL123', 'status' => 'PENDING']]): PaymentIntent
{
    Http::fake(['fastlipa.test/api/create-transaction*' => Http::response($response, 200)]);

    return app(PaymentService::class)->initiate(Branch::first()->id, auth()->user(), PaymentMethod::Mpesa, '0754 112 233', 5000, 'REF-'.Str::random(6));
}

function signedCallback(array $body): TestResponse
{
    $json = json_encode($body);

    return test()->call('POST', '/api/payments/callback/fastlipa', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_FASTLIPA_SIGNATURE' => hash_hmac('sha256', $json, 'whsec'),
    ], $json);
}

it('never marks a payment completed from the initiation response', function () {
    $intent = initiateIntent(['status' => 'success', 'data' => ['tranID' => 'FL1', 'status' => 'COMPLETED']]);
    expect($intent->status)->toBe('processing')->and($intent->provider_reference)->toBe('FL1')->and($intent->phone)->toBe('255754112233');
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer test-key') && $r['number'] === '255754112233' && $r['amount'] === 5000);
});

it('marks rejected initiations failed', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid number'], 422)]);
    expect($this->payments->initiate($this->branch->id, $this->user, PaymentMethod::Mpesa, '0754112233', 100, 'R2')->status)->toBe('failed');
});

it('keeps timed-out initiations pending for reconciliation', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));
    expect($this->payments->initiate($this->branch->id, $this->user, PaymentMethod::Mpesa, '0754112233', 100, 'R3')->status)->toBe('processing');
});

it('is idempotent on reference', function () {
    $a = initiateIntent();
    $b = $this->payments->initiate($this->branch->id, $this->user, PaymentMethod::Mpesa, '0754112233', 5000, $a->reference);
    expect($b->id)->toBe($a->id)->and(PaymentIntent::count())->toBe(1);
});

it('completes intents from signed callbacks and ignores duplicates', function () {
    $intent = initiateIntent();
    signedCallback(['data' => ['reference' => $intent->reference, 'tranID' => 'FL123', 'payment_status' => 'COMPLETED', 'amount' => 5000]])->assertOk();
    expect($intent->fresh()->status)->toBe('completed');

    signedCallback(['data' => ['reference' => $intent->reference, 'tranID' => 'FL123', 'payment_status' => 'FAILED']])->assertOk();
    expect($intent->fresh()->status)->toBe('completed')
        ->and(PaymentCallback::latest('id')->first()->result)->toBe('duplicate');
});

it('rejects callbacks with a bad signature', function () {
    $intent = initiateIntent();
    $this->postJson('/api/payments/callback/fastlipa', ['data' => ['reference' => $intent->reference, 'payment_status' => 'COMPLETED']], ['X-FastLipa-Signature' => 'nope'])
        ->assertStatus(401);
    expect($intent->fresh()->status)->toBe('processing');
});

it('fails underpaid callbacks and keeps unknown statuses pending', function () {
    $intent = initiateIntent();
    signedCallback(['data' => ['reference' => $intent->reference, 'payment_status' => 'WEIRD']])->assertOk();
    expect($intent->fresh()->status)->toBe('processing');
    signedCallback(['data' => ['reference' => $intent->reference, 'payment_status' => 'SUCCESS', 'amount' => 100]])->assertOk();
    expect($intent->fresh()->status)->toBe('failed');
});

it('verifies unsigned callbacks by re-querying the provider', function () {
    setting()->set('payments.fastlipa_webhook_secret', null);
    $intent = initiateIntent();
    Http::fake(['fastlipa.test/api/status-transaction*' => Http::response(['data' => ['tranID' => 'FL123', 'payment_status' => 'COMPLETED', 'amount' => 5000]])]);
    $this->postJson('/api/payments/callback/fastlipa', ['data' => ['reference' => $intent->reference, 'tranID' => 'FL123', 'payment_status' => 'COMPLETED']])->assertOk();
    expect($intent->fresh()->status)->toBe('completed');
});

it('reconciles stuck intents', function () {
    $intent = initiateIntent();
    PaymentIntent::whereKey($intent->id)->update(['created_at' => now()->subMinutes(5)]);
    Http::fake(['fastlipa.test/api/status-transaction*' => Http::response(['data' => ['payment_status' => 'FAILED']])]);
    $this->artisan('dukapos:reconcile-payments')->assertSuccessful();
    expect($intent->fresh()->status)->toBe('failed');
});

it('only accepts confirmed, unused push payments at checkout', function () {
    $product = Product::factory()->create(['retail_price' => 5000, 'cost_price' => 1000]);
    app(StockService::class)->receive($this->branch->id, $product, 10, MovementType::Opening);
    $shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'T']), $this->user, 0);
    $intent = initiateIntent();
    $cart = ['lines' => [['product_id' => $product->id, 'qty' => 1]]];
    $pay = [['method' => 'mpesa', 'amount' => 5000, 'intent_reference' => $intent->reference, 'gateway_status' => 'completed']];

    expect(fn () => app(SaleService::class)->checkout($cart, $pay, $this->user, $shift, (string) Str::uuid()))->toThrow(BusinessRuleException::class);

    signedCallback(['data' => ['reference' => $intent->reference, 'payment_status' => 'COMPLETED', 'amount' => 5000]]);
    $sale = app(SaleService::class)->checkout($cart, $pay, $this->user, $shift, (string) Str::uuid());
    expect($sale->payments->first()->gateway_reference)->toBe('FL123')->and($intent->fresh()->sale_id)->toBe($sale->id);

    expect(fn () => app(SaleService::class)->checkout($cart, $pay, $this->user, $shift, (string) Str::uuid()))->toThrow(BusinessRuleException::class);
});

it('detects mobile networks from the number', function () {
    expect(PhoneNumber::network('0754112233'))->toBe('vodacom')
        ->and(PhoneNumber::network('0688112233'))->toBe('airtel')
        ->and(PhoneNumber::network('0655112233'))->toBe('yas')
        ->and(PhoneNumber::network('0622112233'))->toBe('halotel');
});

it('sends SMS through Beem', function () {
    setting()->set(['sms.driver' => 'beem', 'sms.api_key' => 'k', 'sms.api_secret' => 's', 'sms.sender_id' => 'DUKA']);
    Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 99])]);
    $result = app(SmsGateway::class)->send('0712345678', 'Habari');
    expect($result->ok)->toBeTrue();
    Http::assertSent(fn ($r) => $r['recipients'][0]['dest_addr'] === '255712345678' && $r['source_addr'] === 'DUKA');
});
