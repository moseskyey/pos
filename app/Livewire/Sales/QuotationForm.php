<?php

namespace App\Livewire\Sales;

use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\PicksProducts;
use App\Livewire\Concerns\RequiresApproval;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Pos\CartCalculator;
use App\Services\Pos\PriceResolver;
use App\Services\QuotationService;
use App\Support\BranchContext;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuotationForm extends Component
{
    use PicksProducts, RequiresApproval;

    #[Locked]
    public ?int $quotationId = null;

    public ?int $customerId = null;

    public string $validUntil = '';

    public string $note = '';

    public ?string $cartDiscountType = null;

    public $cartDiscountValue = null;

    public array $lines = [];

    public function mount(?Sale $quotation = null): void
    {
        abort_unless(auth()->user()->can('quotations.manage'), 403);
        $this->validUntil = now()->addDays(14)->toDateString();
        if ($quotation?->exists) {
            $this->quotationId = $quotation->id;
            $this->customerId = $quotation->customer_id;
            $this->validUntil = $quotation->valid_until?->toDateString() ?? $this->validUntil;
            $this->note = (string) $quotation->note;
            $this->cartDiscountType = $quotation->cart_discount_type;
            $this->cartDiscountValue = $quotation->cart_discount_value !== null ? (float) $quotation->cart_discount_value : null;
            foreach ($quotation->items as $item) {
                $this->lines[] = [
                    'product_id' => $item->product_id, 'product_unit_id' => $item->product_unit_id, 'name' => $item->name, 'unit' => $item->unit_name,
                    'qty' => (float) $item->quantity, 'unit_price' => (float) $item->unit_price, 'price_override' => $item->price_tier === 'override',
                    'discount_type' => $item->discount_type, 'discount_value' => $item->discount_value !== null ? (float) $item->discount_value : null,
                    'tax_rate' => (float) $item->tax_rate,
                ];
            }
        }
    }

    public function addProduct(int $productId, mixed $quantity = null): void
    {
        $product = Product::with('unit')->findOrFail($productId);
        $price = app(PriceResolver::class)->resolve($product, null, $quantity ?? 1, $this->customerId ? Customer::find($this->customerId) : null);
        $this->lines[] = [
            'product_id' => $product->id, 'product_unit_id' => null, 'name' => $product->name, 'unit' => $product->unit?->short_name,
            'qty' => (float) ($quantity ?? 1), 'unit_price' => (float) $price['price'], 'price_override' => false,
            'discount_type' => null, 'discount_value' => null, 'tax_rate' => (float) $product->taxRate(),
        ];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines($value, $key): void
    {
        [$index, $field] = array_pad(explode('.', (string) $key), 2, null);
        if ($field === 'unit_price' && isset($this->lines[$index])) {
            $this->lines[$index]['price_override'] = true;
        }
    }

    public function save(?string $reason = null)
    {
        $this->validate([
            'customerId' => ['nullable', 'exists:customers,id'],
            'validUntil' => ['required', 'date', 'after_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ], [], ['lines' => __('products')]);

        $branchId = app(BranchContext::class)->currentId();
        if (! $branchId) {
            $this->dispatch('toast', message: __('Select a single branch in the navbar first.'), type: 'error');

            return null;
        }

        $cart = [
            'lines' => array_map(fn ($l) => $l + ['discount_type' => $l['discount_value'] ? ($l['discount_type'] ?: 'percent') : null], $this->lines),
            'customer_id' => $this->customerId,
            'cart_discount_type' => $this->cartDiscountValue ? ($this->cartDiscountType ?: 'percent') : null,
            'cart_discount_value' => $this->cartDiscountValue ?: null,
            'note' => $this->note ?: null,
        ];
        try {
            $quote = app(QuotationService::class)->save($cart, auth()->user(), $branchId, $this->validUntil, $this->quotationId ? Sale::find($this->quotationId) : null, $this->approvals);
        } catch (ApprovalRequiredException $e) {
            $this->requestApproval($e->action, $e->permission, $e->getMessage(), 'save');

            return null;
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return null;
        }
        session()->flash('success', __('Quotation :n saved.', ['n' => $quote->number]));

        return $this->redirectRoute('quotations.show', $quote);
    }

    public function render()
    {
        $calc = CartCalculator::fromSettings()->calculate(
            array_map(fn ($l) => ['qty' => $l['qty'] ?: 0, 'unit_price' => $l['unit_price'] ?: 0, 'discount_type' => $l['discount_value'] ? ($l['discount_type'] ?: 'percent') : null, 'discount_value' => $l['discount_value'], 'tax_rate' => $l['tax_rate']], $this->lines),
            $this->cartDiscountValue ? ($this->cartDiscountType ?: 'percent') : null, $this->cartDiscountValue
        );

        return view('livewire.sales.quotation-form', [
            'totals' => $calc,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->pluck('name', 'id'),
        ]);
    }
}
