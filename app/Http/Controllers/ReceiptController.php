<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(protected ReceiptService $receipts) {}

    /** Original receipt – available to the cashier right after the sale. */
    public function show(Request $request, Sale $sale): View|RedirectResponse
    {
        $user = $request->user();
        $fresh = $sale->user_id === $user->id && ($sale->completed_at ?? $sale->created_at)->gt(now()->subMinutes(15)) && $sale->reprint_count === 0;
        if (! $fresh) {
            return redirect()->route('receipts.reprint', $sale);
        }

        return view('receipts.thermal', $this->receipts->data($sale) + ['copy' => false, 'embed' => $request->boolean('embed')]);
    }

    public function reprint(Request $request, Sale $sale): View
    {
        abort_unless($request->user()->can('sales.reprint') || $request->user()->can('sales.view_all'), 403);
        $sale->increment('reprint_count');
        activity('sales')->performedOn($sale)->withProperties(['count' => $sale->reprint_count])->log('Receipt reprinted');

        return view('receipts.thermal', $this->receipts->data($sale) + ['copy' => true, 'embed' => $request->boolean('embed')]);
    }

    public function invoice(Request $request, Sale $sale): Response
    {
        abort_unless($request->user()->canAny(['sales.view', 'sales.view_all']), 403);
        $data = $this->receipts->data($sale) + ['docTitle' => $sale->status->value === 'quotation' ? __('Quotation') : __('Tax Invoice'), 'title' => $sale->number, 'copy' => $sale->status->value === 'voided'];

        return Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->stream($sale->number.'.pdf');
    }

    public function deliveryNote(Request $request, Sale $sale): Response
    {
        abort_unless($request->user()->canAny(['sales.view', 'sales.view_all']), 403);
        $data = $this->receipts->data($sale) + ['docTitle' => __('Delivery Note'), 'title' => 'DN '.$sale->number];

        return Pdf::loadView('pdf.delivery-note', $data)->setPaper('a4')->stream('DN-'.$sale->number.'.pdf');
    }

    /** Public signed verification page (QR code target). */
    public function verify(Request $request, string $number): View
    {
        abort_unless($request->hasValidSignature(), 403);
        $sale = Sale::withoutGlobalScopes()->with('branch')->where('number', $number)->firstOrFail();

        return view('receipts.verify', compact('sale'));
    }
}
