<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\GiftCard;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Gift cards (paid for) and vouchers (complimentary). A card's balance is a
 * ledger of gift_card_transactions; the cached balance is changed only here,
 * under a row lock, in the same transaction as the ledger entry.
 */
class GiftCardService
{
    /** No 0/O or 1/I, so codes read cleanly off paper. */
    protected const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(protected ShiftService $shifts) {}

    /**
     * @param  array{value: mixed, kind: string, customer_id?: ?int, expires_on?: ?string, note?: ?string, payment_method?: ?string, reference?: ?string}  $data
     */
    public function issue(array $data, User $user, int $branchId): GiftCard
    {
        $value = Money::round($data['value']);
        if (! Money::isPositive($value)) {
            throw new BusinessRuleException(__('Enter a value greater than zero.'));
        }
        $kind = $data['kind'] === 'voucher' ? 'voucher' : 'gift_card';
        $method = $kind === 'gift_card' ? PaymentMethod::tryFrom((string) ($data['payment_method'] ?? '')) : null;
        if ($kind === 'gift_card') {
            if (! $method || $method->isAccount()) {
                throw new BusinessRuleException(__('Choose how the customer paid for the gift card.'));
            }
            if ($method->needsReference() && empty($data['reference'])) {
                throw new BusinessRuleException(__('Enter the :m transaction reference.', ['m' => $method->label()]));
            }
        }
        $shift = $method?->isCash() ? $this->shifts->current($user, $branchId) : null;
        if ($method?->isCash() && ! $shift) {
            throw new BusinessRuleException(__('Open a shift to take cash for a gift card.'));
        }

        return DB::transaction(function () use ($data, $user, $branchId, $value, $kind, $method, $shift) {
            $card = GiftCard::create([
                'code' => $this->newCode(),
                'kind' => $kind,
                'initial_value' => $value,
                'balance' => $value,
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $branchId,
                'expires_on' => $data['expires_on'] ?? null,
                'note' => $data['note'] ?? null,
                'issued_by' => $user->id,
            ]);
            $card->transactions()->create([
                'branch_id' => $branchId, 'type' => 'issue', 'amount' => $value, 'balance_after' => $value,
                'payment_method' => $method?->value, 'reference' => $data['reference'] ?? null, 'user_id' => $user->id,
            ]);
            if ($shift) {
                $this->shifts->cashMovement($shift, $user, 'in', $value, __('Gift card :c sold', ['c' => $card->displayCode()]), $card);
            }
            activity('gift_cards')->causedBy($user)->performedOn($card)
                ->withProperties(['value' => $value, 'kind' => $kind, 'method' => $method?->value])->log($kind === 'voucher' ? 'Voucher issued' : 'Gift card sold');

            return $card;
        });
    }

    public function find(?string $code): ?GiftCard
    {
        $code = GiftCard::normalizeCode($code);

        return $code === '' ? null : GiftCard::where('code', $code)->first();
    }

    /** Check a card can pay $amount right now; returns the locked card. Call inside a transaction. */
    public function lockForPayment(?string $code, string $amount): GiftCard
    {
        $card = GiftCard::where('code', GiftCard::normalizeCode($code))->lockForUpdate()->first();
        if (! $card) {
            throw new BusinessRuleException(__('Gift card :c was not found.', ['c' => $code]));
        }
        if (! $card->is_active) {
            throw new BusinessRuleException(__('Gift card :c has been deactivated.', ['c' => $card->displayCode()]));
        }
        if ($card->isExpired()) {
            throw new BusinessRuleException(__('Gift card :c expired on :d.', ['c' => $card->displayCode(), 'd' => format_date($card->expires_on)]));
        }
        if (Money::gt($amount, $card->balance)) {
            throw new BusinessRuleException(__('Gift card :c only has :b left.', ['c' => $card->displayCode(), 'b' => money($card->balance)]));
        }

        return $card;
    }

    /** Spend from a card as part of a sale (inside the sale's transaction). */
    public function redeem(int $cardId, string $amount, Sale $sale, User $user): void
    {
        $card = GiftCard::lockForUpdate()->findOrFail($cardId);
        $balance = Money::sub($card->balance, $amount);
        if (Money::isNegative($balance)) {
            throw new BusinessRuleException(__('Gift card :c only has :b left.', ['c' => $card->displayCode(), 'b' => money($card->balance)]));
        }
        $card->update(['balance' => $balance]);
        $card->transactions()->create([
            'branch_id' => $sale->branch_id, 'type' => 'redeem', 'amount' => Money::negate($amount), 'balance_after' => $balance,
            'sale_id' => $sale->id, 'user_id' => $user->id,
        ]);
    }

    /** Put gift card payments back on the card when a sale is voided (inside the void transaction). */
    public function restoreForSale(Sale $sale, User $user): void
    {
        foreach ($sale->payments()->where('method', PaymentMethod::GiftCard->value)->get() as $payment) {
            $cardId = $payment->meta['gift_card_id'] ?? null;
            $card = $cardId ? GiftCard::lockForUpdate()->find($cardId) : null;
            if (! $card) {
                continue;
            }
            $balance = Money::add($card->balance, $payment->amount);
            $card->update(['balance' => $balance]);
            $card->transactions()->create([
                'branch_id' => $sale->branch_id, 'type' => 'void', 'amount' => Money::round($payment->amount), 'balance_after' => $balance,
                'sale_id' => $sale->id, 'user_id' => $user->id,
            ]);
        }
    }

    public function setActive(GiftCard $card, bool $active, User $user): GiftCard
    {
        $card->update(['is_active' => $active]);
        activity('gift_cards')->causedBy($user)->performedOn($card)->log($active ? 'Gift card reactivated' : 'Gift card deactivated');

        return $card;
    }

    protected function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }
}
