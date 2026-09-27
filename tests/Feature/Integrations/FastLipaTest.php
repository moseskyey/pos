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
use App\Notifications\SystemAlert;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * Payload shapes are taken from FastLipa's docs and real production webhooks:
 * the webhook uses "tranID" + "status" and a string amount, while the status
 * endpoint uses "tranid" + "payment_status".
 */

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    setting()->set(['payments.gateway' => 'fastlipa', 'payments.fastlipa_api_key' => 'test-key', 'payments.fastlipa_base_url' => 'https://api.fastlipa.test', 'payments.fastlipa_webhook_secret' => null]);
    $this->payments = app(PaymentService::class);

    // One fake for the whole test; tests change what FastLipa "says" through $this->fl.
    $this->fl = [
        'create' => fn () => Http::response(['status' => true, 'message' => 'Payment initiated', 'data' => ['tranID' => 'TXN9K3XYZAB12', 'amount' => 5000, 'number' => '255754112233', 'payment_status' => 'PENDING']]),
        'status' => 'PENDING',
        'amount' => 5000,
    ];
    Http::fake(function (HttpRequest $request) {
        if (str_contains($request->url(), '/v1/transaction/create')) {
            return ($this->fl['create'])($request);
        }
        if (str_contains($request->url(), '/v1/transaction/status')) {
            return Http::response(['status' => true, 'message' => 'Payment status retrieved', 'data' => [
                'tranid' => $request['tranid'], 'payment_status' => $this->fl['status'], 'amount' => $this->fl['amount'], 'network' => 'VODACOM', 'time' => now()->toIso8601String(),
            ]]);
        }

        return null; // fall through to other fakes (e.g. Beem)
    });
});

function initiateIntent(int $amount = 5000): PaymentIntent
{
    return app(PaymentService::class)->initiate(Branch::first()->id, auth()->user(), PaymentMethod::Mpesa, '0754 112 233', $amount, 'DP-'.Str::upper(Str::random(10)));
}

/** A webhook exactly as FastLipa sends it (see production logs). */
function fastLipaWebhook(PaymentIntent $intent, string $event, ?string $amount = null, array $headers = []): TestResponse
{
    $status = $event === 'payment.completed' ? 'COMPLETED' : 'FAILED';

    return test()->postJson(route('payments.callback', 'fastlipa'), [
        'event' => $event,
        'timestamp' => now()->toIso8601String(),
        'data' => [
            'tranID' => $intent->provider_reference, 'reference' => $intent->reference, 'amount' => $amount ?? (string) (int) $intent->amount,
            'number' => $intent->phone, 'network' => 'VODACOM', 'status' => $status, 'timestamp' => now()->toIso8601String(),
        ],
    ], $headers);
}

it('initiates a push with the documented request and never completes from the response', function () {
    $intent = initiateIntent();

    expect($intent->status)->toBe('processing')->and($intent->provider_reference)->toBe('TXN9K3XYZAB12')->and($intent->phone)->toBe('255754112233');
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api.fastlipa.test/v1/transaction/create'
        && $r->hasHeader('Authorization', 'Bearer test-key') && $r['number'] === '255754112233' && $r['amount'] === 5000
        && $r['reference'] === $intent->reference && $r['name'] !== '' && $r['webhook_url'] === route('payments.callback', 'fastlipa'));

    $this->fl['create'] = fn () => Http::response(['status' => true, 'data' => ['tranID' => 'X2', 'payment_status' => 'COMPLETED']]);
    expect(initiateIntent()->status)->toBe('processing');
});

it('fails refused initiations and keeps timeouts pending', function () {
    $this->fl['create'] = fn () => Http::response(['status' => false, 'message' => 'Invalid number'], 200);
    $refused = initiateIntent();
    expect($refused->status)->toBe('failed')->and($refused->message)->toBe('Invalid number')->and($refused->recheckable())->toBeFalse();

    $this->fl['create'] = fn () => Http::response(['status' => false, 'message' => 'Unauthenticated'], 401);
    expect(initiateIntent()->status)->toBe('failed');

    $this->fl['create'] = fn () => throw new ConnectionException('timeout');
    expect(initiateIntent()->status)->toBe('processing');
});

it('is idempotent on reference', function () {
    $a = initiateIntent();
    $b = $this->payments->initiate($this->branch->id, $this->user, PaymentMethod::Mpesa, '0754112233', 5000, $a->reference);
    expect($b->id)->toBe($a->id)->and(PaymentIntent::count())->toBe(1);
    Http::assertSentCount(1);
});

it('queries status with GET /v1/transaction/status?tranid=', function () {
    $intent = initiateIntent();
    $this->fl['status'] = 'COMPLETED';
    expect($this->payments->refresh($intent)->status)->toBe('completed');
    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_starts_with($r->url(), 'https://api.fastlipa.test/v1/transaction/status?tranid=TXN9K3XYZAB12'));
});

it('handles failed-then-completed for the same transaction, as seen in production', function () {
    Notification::fake();
    $intent = initiateIntent(360000);

    // 12:00 – "payment.failed" delivered three times; FastLipa's status endpoint agrees.
    $this->fl['status'] = 'FAILED';
    $this->fl['amount'] = '360000';
    foreach (range(1, 3) as $i) {
        fastLipaWebhook($intent, 'payment.failed')->assertOk();
    }
    $intent->refresh();
    expect($intent->status)->toBe('failed')->and($intent->recheckable())->toBeTrue()->and($intent->isTerminal())->toBeFalse();

    // 12:04 – "payment.completed" delivered three times. The money arrived after all.
    $this->fl['status'] = 'COMPLETED';
    foreach (range(1, 3) as $i) {
        fastLipaWebhook($intent, 'payment.completed')->assertOk();
    }
    $intent->refresh();
    expect($intent->status)->toBe('completed')
        ->and($intent->late_completed_at)->not->toBeNull()
        ->and($intent->isTerminal())->toBeTrue()
        ->and(PaymentCallback::where('result', 'duplicate')->count())->toBe(2);

    // Nobody used it for a sale yet: the cashier and managers are told once.
    Notification::assertSentTo($this->user, SystemAlert::class, fn ($n) => str_contains($n->message, '360,000'));
    Notification::assertSentTimes(SystemAlert::class, 1);
});

it('never trusts the webhook body: the status endpoint decides', function () {
    $intent = initiateIntent();
    $this->fl['status'] = 'PENDING';
    fastLipaWebhook($intent, 'payment.completed')->assertOk();
    expect($intent->fresh()->status)->toBe('processing');

    $this->fl['status'] = 'COMPLETED';
    $this->fl['amount'] = 100; // provider collected less than requested
    fastLipaWebhook($intent, 'payment.completed')->assertOk();
    $intent->refresh();
    expect($intent->status)->toBe('failed')->and($intent->recheckable())->toBeFalse();
});

it('rejects a wrong signature but accepts unsigned webhooks via status query', function () {
    setting()->set('payments.fastlipa_webhook_secret', 'whsec');
    $intent = initiateIntent();
    $this->fl['status'] = 'COMPLETED';

    fastLipaWebhook($intent, 'payment.completed', headers: ['X-FastLipa-Signature' => 'forged'])->assertStatus(401);
    expect($intent->fresh()->status)->toBe('processing');

    fastLipaWebhook($intent, 'payment.completed')->assertOk();
    expect($intent->fresh()->status)->toBe('completed');
});

it('acknowledges webhooks for references it does not know', function () {
    initiateIntent();
    $this->postJson(route('payments.callback', 'fastlipa'), ['event' => 'payment.completed', 'data' => [
        'tranID' => 'EUVO6K1790510391', 'reference' => 'CP-AYWCTFLRBF', 'amount' => '360000', 'status' => 'COMPLETED',
    ]])->assertOk();
    $this->postJson(route('payments.callback', 'fastlipa'), ['event' => 'payment.completed'])->assertOk();

    expect(PaymentCallback::where('result', 'unknown_reference')->count())->toBe(2)
        ->and(PaymentIntent::first()->status)->toBe('processing');
});

it('reconciles stuck and recently failed intents, then stops', function () {
    $stuck = initiateIntent();
    PaymentIntent::whereKey($stuck->id)->update(['created_at' => now()->subMinutes(5)]);
    $this->fl['status'] = 'FAILED';
    $this->artisan('dukapos:reconcile-payments')->assertSuccessful();
    expect($stuck->fresh()->status)->toBe('failed');

    // No webhook this time: the sweep alone picks up the late completion.
    $this->fl['status'] = 'COMPLETED';
    $this->artisan('dukapos:reconcile-payments')->assertSuccessful();
    expect($stuck->fresh()->status)->toBe('completed');

    // A failure older than the re-check window is left alone.
    $old = initiateIntent();
    $this->fl['status'] = 'FAILED';
    $this->payments->refresh($old);
    PaymentIntent::whereKey($old->id)->update(['recheck_until' => now()->subMinute()]);
    $this->fl['status'] = 'COMPLETED';
    $this->artisan('dukapos:reconcile-payments')->assertSuccessful();
    expect($old->fresh()->status)->toBe('failed');
});

it('only accepts confirmed, unused push payments at checkout', function () {
    $product = Product::factory()->create(['retail_price' => 5000, 'cost_price' => 1000]);
    app(StockService::class)->receive($this->branch->id, $product, 10, MovementType::Opening);
    $shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'T']), $this->user, 0);
    $intent = initiateIntent();
    $cart = ['lines' => [['product_id' => $product->id, 'qty' => 1]]];
    $pay = [['method' => 'mpesa', 'amount' => 5000, 'intent_reference' => $intent->reference, 'gateway_status' => 'completed']];

    expect(fn () => app(SaleService::class)->checkout($cart, $pay, $this->user, $shift, (string) Str::uuid()))->toThrow(BusinessRuleException::class);

    $this->fl['status'] = 'COMPLETED';
    fastLipaWebhook($intent, 'payment.completed')->assertOk();
    $sale = app(SaleService::class)->checkout($cart, $pay, $this->user, $shift, (string) Str::uuid());
    expect($sale->payments->first()->gateway_reference)->toBe('TXN9K3XYZAB12')->and($intent->fresh()->sale_id)->toBe($sale->id);

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
