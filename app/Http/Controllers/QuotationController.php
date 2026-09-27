<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('quotations.manage'), 403);

        return view('quotations.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('quotations.manage'), 403);

        return view('quotations.form', ['quotation' => null]);
    }

    public function edit(Request $request, Sale $quotation): View
    {
        abort_unless($request->user()->can('quotations.manage') && $quotation->status === SaleStatus::Quotation, 403);
        $quotation->load('items');

        return view('quotations.form', compact('quotation'));
    }

    public function show(Request $request, Sale $quotation): View
    {
        abort_unless($request->user()->can('quotations.manage'), 403);
        abort_unless(in_array($quotation->status, [SaleStatus::Quotation, SaleStatus::Converted], true), 404);
        $quotation->load(['items', 'customer', 'cashier', 'convertedSale']);

        return view('quotations.show', compact('quotation'));
    }

    public function convert(Request $request, Sale $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('pos.access') && $quotation->status === SaleStatus::Quotation, 403);

        return redirect()->route('pos', ['quotation' => $quotation->id]);
    }
}
