<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\URL;

/**
 * Customer-facing links and messages for WhatsApp. PDFs are served through
 * signed, expiring URLs so customers can open them without an account.
 */
class ShareService
{
    public const LINK_DAYS = 30;

    public function invoiceUrl(Sale $sale): string
    {
        return URL::temporarySignedRoute('share.invoice', now()->addDays(self::LINK_DAYS), ['sale' => $sale->id]);
    }

    public function statementUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute('share.statement', now()->addDays(self::LINK_DAYS), ['customer' => $customer->id]);
    }

    public function saleText(Sale $sale): string
    {
        if ($sale->status->value === 'quotation') {
            return __(':biz: Quotation :n, total :total, valid until :d. View: :url', [
                'biz' => setting('business.name'), 'n' => $sale->number, 'total' => money($sale->total),
                'd' => $sale->valid_until ? format_date($sale->valid_until) : '—', 'url' => $this->invoiceUrl($sale),
            ]);
        }

        return app(ReceiptService::class)->smsText($sale)."\n".__('Invoice: :url', ['url' => $this->invoiceUrl($sale)]);
    }

    public function statementText(Customer $customer): string
    {
        return app(CustomerStatementService::class)->reminderText($customer)."\n".__('Statement: :url', ['url' => $this->statementUrl($customer)]);
    }

    public function saleLink(Sale $sale): string
    {
        return WhatsApp::link($sale->customer?->phone, $this->saleText($sale));
    }

    public function statementLink(Customer $customer): string
    {
        return WhatsApp::link($customer->phone, $this->statementText($customer));
    }
}
