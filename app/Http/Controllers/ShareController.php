<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Services\CustomerStatementService;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public documents behind signed, expiring links (shared via WhatsApp).
 * The "signed" middleware rejects tampered or expired URLs.
 */
class ShareController extends Controller
{
    public function invoice(int $sale, ReceiptService $receipts): Response
    {
        $sale = Sale::withoutGlobalScopes()->whereKey($sale)->whereIn('status', ['completed', 'quotation', 'layaway', 'converted'])->firstOrFail();
        $data = $receipts->data($sale) + ['docTitle' => $sale->status->value === 'quotation' ? __('Quotation') : __('Tax Invoice'), 'title' => $sale->number, 'copy' => false];

        return Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->stream($sale->number.'.pdf');
    }

    public function statement(Customer $customer, CustomerStatementService $statements): Response
    {
        $data = $statements->statement($customer, Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now());

        return Pdf::loadView('pdf.customer-statement', $data + ['title' => __('Statement'), 'docTitle' => __('Customer statement')])
            ->setPaper('a4')->stream('statement-'.Str::slug($customer->name).'.pdf');
    }
}
