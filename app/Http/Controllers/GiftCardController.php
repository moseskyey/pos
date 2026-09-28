<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\GiftCardRequest;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GiftCardController extends Controller
{
    public function __construct(protected GiftCardService $cards) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GiftCard::class);

        return view('gift-cards.index', [
            'outstanding' => GiftCard::query()->where('is_active', true)->where('balance', '>', 0)
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))->sum('balance'),
            'count' => GiftCard::count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GiftCard::class);

        return view('gift-cards.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->pluck('name', 'id'),
            'methods' => collect(PaymentMethod::enabled())->reject(fn (PaymentMethod $m) => $m->isAccount())
                ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()]),
        ]);
    }

    public function store(GiftCardRequest $request, BranchContext $context): RedirectResponse
    {
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        try {
            $card = $this->cards->issue($request->validated(), $request->user(), $branchId);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('gift-cards.show', $card)
            ->with('success', __(':kind :c issued for :v.', ['kind' => $card->kind === 'voucher' ? __('Voucher') : __('Gift card'), 'c' => $card->displayCode(), 'v' => money($card->initial_value)]));
    }

    public function show(Request $request, GiftCard $giftCard): View
    {
        $this->authorize('view', $giftCard);
        $giftCard->load(['customer', 'branch', 'issuer', 'transactions.sale', 'transactions.user', 'transactions.branch']);

        return view('gift-cards.show', ['card' => $giftCard]);
    }

    public function toggle(Request $request, GiftCard $giftCard): RedirectResponse
    {
        $this->authorize('update', $giftCard);
        $this->cards->setActive($giftCard, ! $giftCard->is_active, $request->user());

        return back()->with('success', $giftCard->is_active ? __('Gift card reactivated.') : __('Gift card deactivated. It can no longer be spent.'));
    }

    public function print(Request $request, GiftCard $giftCard): View
    {
        $this->authorize('view', $giftCard);

        return view('gift-cards.print', ['card' => $giftCard]);
    }
}
