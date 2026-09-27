<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\QuotationService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 30 days of realistic sales history for both branches, run through the real
 * services (shifts, payments, credit, returns, voids, quotations, layaway).
 */
class SalesSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);
        $sales = app(SaleService::class);
        $shifts = app(ShiftService::class);
        $stock = app(StockService::class);
        $products = Product::query()->active()->sellable()->with(['units', 'unit'])->get();
        $customers = Customer::all();
        $manager = User::where('email', 'owner@dukapos.test')->first();
        Carbon::setTestNow();
        $realNow = now();
        $today = now()->startOfDay();

        $cashiers = [
            'DSM01' => User::where('email', 'cashier@dukapos.test')->first(),
            'DSM02' => User::where('email', 'cashier.mbezi@dukapos.test')->first(),
        ];

        foreach (Branch::orderBy('id')->get() as $branch) {
            $cashier = $cashiers[$branch->code] ?? $manager;
            $register = Register::withoutGlobalScopes()->where('branch_id', $branch->id)->orderBy('id')->first();

            for ($day = 29; $day >= 0; $day--) {
                $date = $today->copy()->subDays($day);
                Carbon::setTestNow($date->copy()->setTime(7, 45));
                Auth::login($cashier);
                $shift = $shifts->open($register, $cashier, 50000);

                $weekend = $date->isWeekend();
                $count = mt_rand(6, 11) + ($weekend ? 4 : 0) + ($branch->code === 'DSM01' ? 3 : 0);
                $hours = collect(range(1, $count))->map(fn () => $this->busyHour())->sort()->values()
                    ->when($day === 0, fn ($h) => $h->filter(fn ($hour) => $hour < max(9, $realNow->hour))->values());

                foreach ($hours as $i => $hour) {
                    Carbon::setTestNow($date->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59)));
                    $lines = [];
                    foreach ($products->random(mt_rand(1, 4)) as $product) {
                        $qty = $product->unit?->allow_decimal ? mt_rand(1, 4) / 2 : mt_rand(1, 3);
                        if (Money::lt($stock->available($branch->id, $product->id), $qty + 2)) {
                            continue;
                        }
                        $lines[] = ['product_id' => $product->id, 'qty' => $qty];
                    }
                    if (! $lines) {
                        continue;
                    }

                    $customer = mt_rand(1, 100) <= 30 ? $customers->random() : null;
                    $cart = ['lines' => $lines, 'customer_id' => $customer?->id];
                    if (mt_rand(1, 100) <= 8) {
                        $cart['cart_discount_type'] = 'percent';
                        $cart['cart_discount_value'] = 5;
                    }

                    try {
                        $total = $sales->priceLines($lines, $customer, $cashier)['lines'];
                        $amount = Money::sum($total, fn ($l) => Money::mul($l['qty'], $l['unit_price']));
                        $amount = isset($cart['cart_discount_value']) ? Money::sub($amount, Money::percent($amount, 5)) : $amount;
                        $payments = $this->payments($amount, $customer);
                        $sales->checkout($cart, $payments, $cashier, $shift, (string) Str::uuid(), ['discount' => $manager->id, 'credit_limit' => $manager->id]);
                    } catch (BusinessRuleException) {
                        continue;
                    }
                }

                // Occasional void and cash movements.
                if ($day > 0 && mt_rand(1, 100) <= 20) {
                    $last = Sale::withoutGlobalScopes()->where('shift_id', $shift->id)->where('status', 'completed')->latest('id')->first();
                    if ($last) {
                        Carbon::setTestNow($last->created_at->copy()->addMinutes(3));
                        $sales->void($last, $manager, 'Customer changed mind', $cashier);
                    }
                }
                if (mt_rand(1, 100) <= 40) {
                    Carbon::setTestNow($date->copy()->setTime(13, 10));
                    try {
                        $shifts->cashMovement($shift, $cashier, 'out', [2000, 5000, 3000][mt_rand(0, 2)], ['Usafiri (transport)', 'Maji ya kunywa', 'Vocha ya simu'][mt_rand(0, 2)]);
                    } catch (BusinessRuleException) {
                    }
                }

                if ($day > 0) {
                    Carbon::setTestNow($date->copy()->setTime(20, 30));
                    $expected = $shifts->expectedCash($shift);
                    $variance = [0, 0, 0, -500, 500, -1000, -6000][mt_rand(0, 6)];
                    $shifts->close($shift, $cashier, Money::add($expected, $variance), [], $variance ? 'Counted twice' : null);
                }
            }
        }

        Carbon::setTestNow();
        $this->extras($sales, $manager);
        Carbon::setTestNow();
        Auth::logout();
    }

    protected function busyHour(): int
    {
        $weighted = [8, 9, 10, 10, 11, 12, 12, 13, 13, 14, 15, 16, 17, 17, 18, 18, 18, 19, 19, 20];

        return $weighted[array_rand($weighted)];
    }

    protected function payments(string $amount, ?Customer $customer): array
    {
        $roll = mt_rand(1, 100);
        if ($customer && $roll <= 20 && Money::lte(Money::add($customer->fresh()->balance, $amount), $customer->credit_limit)) {
            return [['method' => 'credit', 'amount' => $amount]];
        }
        if ($roll <= 55) {
            $tender = Money::roundToNearest(Money::add($amount, mt_rand(0, 1) ? 0 : 2000), 1000);

            return [['method' => 'cash', 'amount' => Money::max($tender, $amount)]];
        }
        $method = [PaymentMethod::Mpesa, PaymentMethod::Mpesa, PaymentMethod::TigoPesa, PaymentMethod::Airtel, PaymentMethod::HaloPesa, PaymentMethod::Card][mt_rand(0, 5)];
        if ($roll >= 95) {
            $half = Money::roundToNearest(Money::div($amount, 2), 100);

            return [['method' => 'mpesa', 'amount' => $half, 'reference' => strtoupper(Str::random(10))], ['method' => 'cash', 'amount' => Money::sub($amount, $half)]];
        }

        return [['method' => $method->value, 'amount' => $amount, 'reference' => strtoupper(Str::random(10))]];
    }

    /** Returns, customer payments, a quotation and a layaway. */
    protected function extras(SaleService $sales, User $manager): void
    {
        $kariakoo = Branch::where('code', 'DSM01')->first();
        $cashier = User::where('email', 'cashier@dukapos.test')->first();
        Carbon::setTestNow(now()->setTime(10, 15));
        Auth::login($manager);

        // A return on a recent sale.
        $sale = Sale::withoutGlobalScopes()->where('branch_id', $kariakoo->id)->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(5))->whereNull('customer_id')->with('items')->first();
        if ($sale) {
            Auth::login($cashier);
            $item = $sale->items->first();
            try {
                app(ReturnService::class)->process($sale, [$item->id => ['quantity' => 1, 'condition' => 'restock']], 'Bidhaa ina kasoro (defective)', 'cash', $cashier, null, ['return' => $manager->id]);
            } catch (BusinessRuleException) {
            }
        }

        // Customer debt repayments.
        Auth::login($manager);
        foreach (Customer::where('balance', '>', 0)->take(3)->get() as $customer) {
            app(CustomerPaymentService::class)->receive($customer, Money::roundToNearest(Money::div($customer->balance, 2), 1000), PaymentMethod::Mpesa, $manager, $kariakoo->id, strtoupper(Str::random(10)));
        }

        // A quotation for a school.
        $school = Customer::where('name', 'Shule ya Msingi Upendo')->first();
        $items = Product::query()->whereIn('name', ['Unga wa Sembe 5kg', 'Sukari 2kg', 'Mafuta ya Kupikia Korie 3L', 'Mchele Pride 5kg'])->get();
        if ($school && $items->count()) {
            app(QuotationService::class)->save([
                'lines' => $items->map(fn ($p) => ['product_id' => $p->id, 'qty' => 10])->all(),
                'customer_id' => $school->id,
                'note' => 'Bei hii ni halali kwa siku 14. Delivery within Dar es Salaam included.',
            ], $manager, $kariakoo->id, now()->addDays(14)->toDateString());
        }

        // A layaway (deposit) for a phone.
        $phone = Product::where('name', 'Tecno Spark 20')->first();
        $customer = Customer::where('name', 'Peter Mushi')->first();
        $shift = app(ShiftService::class)->current($cashier, $kariakoo->id);
        if ($phone && $customer && $shift) {
            Auth::login($cashier);
            try {
                $sales->checkout(['lines' => [['product_id' => $phone->id, 'qty' => 1]], 'customer_id' => $customer->id],
                    [['method' => 'cash', 'amount' => 100000]], $cashier, $shift, (string) Str::uuid(), [], 'layaway');
            } catch (BusinessRuleException) {
            }
        }
    }
}
