<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailSaleDocumentRequest;
use App\Models\Sale;
use App\Services\EscPosReceiptService;
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
        $this->authorize('reprint', $sale);
        $sale->increment('reprint_count');
        activity('sales')->performedOn($sale)->withProperties(['count' => $sale->reprint_count])->log('Receipt reprinted');

        return view('receipts.thermal', $this->receipts->data($sale) + ['copy' => true, 'embed' => $request->boolean('embed')]);
    }

    public function invoice(Request $request, Sale $sale): Response
    {
        $this->authorize('document', $sale);
        $data = $this->receipts->data($sale) + ['docTitle' => $sale->status->value === 'quotation' ? __('Quotation') : __('Tax Invoice'), 'title' => $sale->number, 'copy' => $sale->status->value === 'voided'];

        return Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->stream($sale->number.'.pdf');
    }

    /** Email the invoice (or quotation) PDF to the customer. */
    public function email(EmailSaleDocumentRequest $request, Sale $sale): RedirectResponse
    {
        abort_if(in_array($sale->status->value, ['held', 'converted'], true), 404);
        $data = $request->validated();
        $this->receipts->sendEmail($sale, $data['email'], $request->user(), $data['message'] ?? null);

        return back()->with('success', __('Emailed to :e.', ['e' => $data['email']]));
    }

    public function deliveryNote(Request $request, Sale $sale): Response
    {
        $this->authorize('document', $sale);
        $data = $this->receipts->data($sale) + ['docTitle' => __('Delivery Note'), 'title' => 'DN '.$sale->number];

        return Pdf::loadView('pdf.delivery-note', $data)->setPaper('a4')->stream('DN-'.$sale->number.'.pdf');
    }

    /**
     * Raw ESC/POS bytes for direct printing. Same rules as the HTML receipt:
     * the cashier's own fresh sale prints as the original (and may open the
     * drawer); anything else is a logged reprint marked COPY.
     */
    public function escpos(Request $request, Sale $sale, EscPosReceiptService $escpos): Response
    {
        $user = $request->user();
        $fresh = $sale->user_id === $user->id && ($sale->completed_at ?? $sale->created_at)->gt(now()->subMinutes(15)) && $sale->reprint_count === 0;
        $drawer = $fresh && $request->boolean('drawer') && $escpos->shouldOpenDrawer($sale);
        if ($request->boolean('drawer_only')) {
            return response($drawer ? $escpos->drawerOnly() : '', 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
        }
        if (! $fresh) {
            $this->authorize('reprint', $sale);
            $sale->increment('reprint_count');
            activity('sales')->performedOn($sale)->withProperties(['count' => $sale->reprint_count, 'mode' => 'escpos'])->log('Receipt reprinted');
        }
        $bytes = $escpos->render($sale, copy: ! $fresh, openDrawer: $drawer);

        return response($bytes, 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    /** Open the cash drawer without a sale ("no sale"). Logged for loss prevention. */
    public function openDrawer(Request $request, EscPosReceiptService $escpos): Response
    {
        $this->authorize('cash.movements');
        activity('cash')->causedBy($request->user())->withProperties(['branch_id' => current_branch()?->id])->log('Cash drawer opened (no sale)');

        return response($escpos->drawerOnly(), 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    /** Public signed verification page (QR code target). */
    public function verify(Request $request, string $number): View
    {
        abort_unless($request->hasValidSignature(), 403);
        $sale = Sale::withoutGlobalScopes()->with('branch')->where('number', $number)->firstOrFail();

        return view('receipts.verify', compact('sale'));
    }
}
