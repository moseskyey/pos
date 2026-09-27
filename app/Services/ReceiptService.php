<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Mail\SaleDocumentMail;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\QrCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function data(Sale $sale): array
    {
        $sale->loadMissing(['items', 'payments', 'customer', 'cashier', 'branch', 'register']);
        $branch = $sale->branch;
        $taxBreakdown = $sale->items->groupBy(fn ($i) => (string) (float) $i->tax_rate)
            ->map(fn ($items, $rate) => [
                'rate' => $rate,
                'taxable' => Money::sum($items, fn ($i) => Money::sub($i->netTotal(), $i->tax_amount)),
                'tax' => Money::sum($items, 'tax_amount'),
            ])->values();

        return [
            'sale' => $sale,
            'branch' => $branch,
            'header' => $branch?->receipt_header ?: setting('receipt.header'),
            'footer' => $branch?->receipt_footer ?: setting('receipt.footer'),
            'paper' => setting('receipt.paper', '80mm'),
            'taxBreakdown' => $taxBreakdown,
            'qr' => setting('receipt.show_qr', true) ? QrCode::dataUri($sale->fiscal_qr ?: $sale->verificationUrl(), 140) : null,
            'logo' => $this->logo(),
        ];
    }

    public function logo(): ?string
    {
        $path = setting('business.logo');
        if (! setting('receipt.show_logo', true) || ! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path));
    }

    public function smsText(Sale $sale): string
    {
        return __(':biz: Receipt :n, :date. Total :total, paid :paid:balance. Asante!', [
            'biz' => setting('business.name'),
            'n' => $sale->number,
            'date' => $sale->created_at->format('d/m/Y H:i'),
            'total' => money($sale->total),
            'paid' => money($sale->paid_total),
            'balance' => $sale->balance_due > 0 ? ', '.__('balance :b', ['b' => money($sale->balance_due)]) : '',
        ]);
    }

    /** Queue the invoice / quotation PDF to an email address and log who sent it. */
    public function sendEmail(Sale $sale, string $email, User $user, ?string $note = null): void
    {
        Mail::to($email)->queue(new SaleDocumentMail($sale, $note));
        activity('sales')->causedBy($user)->performedOn($sale)->withProperties(['email' => $email])
            ->log($sale->status->value === 'quotation' ? 'Quotation emailed' : 'Invoice emailed');
    }

    public function sendSms(Sale $sale): bool
    {
        $phone = $sale->customer?->phone;
        if (! $phone) {
            return false;
        }
        SendSms::dispatch($phone, $this->smsText($sale))->afterCommit();
        activity('sales')->performedOn($sale)->withProperties(['to' => $phone])->log('SMS receipt queued');

        return true;
    }
}
