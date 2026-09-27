<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\EscPos\Printer;
use App\Support\Money;
use App\Support\PhoneNumber;

/**
 * Renders a sale as ESC/POS bytes for direct thermal printing, mirroring
 * resources/views/receipts/thermal.blade.php.
 */
class EscPosReceiptService
{
    public function __construct(protected ReceiptService $receipts) {}

    public function render(Sale $sale, bool $copy = false, bool $openDrawer = false): string
    {
        $data = $this->receipts->data($sale);
        $branch = $data['branch'];
        $p = Printer::forPaper($data['paper']);
        $m = fn ($v) => money($v, false);

        $p->align('center')->bold()->large()->text((string) setting('business.name'))->large(false)->bold(false);
        $p->text(trim(($branch?->name ?? '').($branch?->address ? ', '.$branch->address : '')));
        if ($phone = $branch?->phone ?: setting('business.phone')) {
            $p->text(__('Tel').': '.PhoneNumber::display($phone));
        }
        if (setting('business.tin')) {
            $p->text('TIN: '.setting('business.tin').(setting('business.vrn') ? '  VRN: '.setting('business.vrn') : ''));
        }
        if ($data['header']) {
            $p->text($data['header']);
        }
        if ($copy) {
            $p->bold()->text('*** '.__('COPY').' ***')->bold(false);
        }
        $p->align('left')->rule();

        $p->row($sale->status->value === 'quotation' ? __('Quotation') : __('Receipt'), $sale->number)
            ->row(__('Date'), $sale->created_at->format('d/m/Y H:i'))
            ->row(__('Cashier'), (string) $sale->cashier?->name);
        if ($sale->register) {
            $p->row(__('Till'), $sale->register->name);
        }
        if ($sale->customer) {
            $p->row(__('Customer'), $sale->customer->name);
            if ($sale->customer->tin) {
                $p->row(__('Customer TIN'), $sale->customer->tin);
            }
        }
        $p->rule();

        foreach ($sale->items as $item) {
            $p->text($item->name);
            $p->row('  '.qty($item->quantity).' '.$item->unit_name.' x '.$m($item->unit_price), $m(Money::mul($item->quantity, $item->unit_price)));
            if ($item->discount_amount > 0) {
                $p->row('  '.__('Discount'), '-'.$m($item->discount_amount));
            }
        }
        $p->rule();

        $p->row(__('Subtotal'), $m($sale->subtotal));
        if ($sale->discount_total > 0) {
            $p->row(__('Discount'), '-'.$m($sale->discount_total));
        }
        foreach ($data['taxBreakdown'] as $t) {
            if ((float) $t['rate'] > 0) {
                $p->row(__('VAT :r%', ['r' => $t['rate']]).(setting('tax.prices_include_vat') ? ' ('.__('incl.').')' : ''), $m($t['tax']));
            }
        }
        if ($sale->rounding != 0) {
            $p->row(__('Rounding'), $m($sale->rounding));
        }
        $p->bold()->large()->row(__('TOTAL'), $m($sale->total), intdiv($p->width, 2))->large(false)->bold(false);
        $p->rule();

        foreach ($sale->payments as $payment) {
            $p->row($payment->method->label().($payment->reference ? ' ('.$payment->reference.')' : ''), $m(data_get($payment->meta, 'tendered', $payment->amount)));
            if (data_get($payment->meta, 'currency') === 'USD') {
                $p->text('  US$ '.number_format((float) $payment->meta['foreign_amount'], 2).' @ '.$m($payment->meta['rate']));
            }
        }
        if ($sale->change_due > 0) {
            $p->bold()->row(__('Change'), $m($sale->change_due))->bold(false);
        }
        if ($sale->balance_due > 0) {
            $p->bold()->row(__('Balance due'), $m($sale->balance_due))->bold(false);
        }
        if ($sale->note) {
            $p->rule()->text($sale->note);
        }
        if ($sale->status->value === 'voided') {
            $p->rule()->align('center')->bold()->text('*** '.__('VOIDED').' ***')->bold(false)->align('left');
        }
        $p->rule()->align('center');
        if ($data['footer']) {
            $p->text($data['footer']);
        }
        if ($sale->fiscal_code) {
            $p->text(__('Verification code').': '.$sale->fiscal_code);
        }
        if (setting('receipt.show_qr', true)) {
            $p->feed(1)->qr($sale->fiscal_qr ?: $sale->verificationUrl(), $data['paper'] === '58mm' ? 4 : 5);
        }
        $p->text(__('Powered by DukaPOS'))->feed(3)->cut();

        if ($openDrawer) {
            $p->openDrawer();
        }

        return $p->bytes();
    }

    /** Open the drawer only for sales where cash changed hands. */
    public function shouldOpenDrawer(Sale $sale): bool
    {
        return (bool) setting('receipt.drawer_kick', true)
            && ($sale->payments->contains(fn ($p) => $p->method->isCash()) || $sale->change_due > 0);
    }

    public function drawerOnly(): string
    {
        return (new Printer)->openDrawer()->bytes();
    }
}
