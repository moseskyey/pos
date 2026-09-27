<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['sales.return', 'sales.view_all']), 403);

        return view('returns.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canAny(['sales.return', 'pos.access']), 403);

        return view('returns.create', ['saleId' => $request->integer('sale') ?: null]);
    }

    public function show(Request $request, SaleReturn $return): View
    {
        abort_unless($request->user()->canAny(['sales.return', 'sales.view_all']) || $return->user_id === $request->user()->id, 403);
        $return->load(['items.product', 'sale', 'customer', 'user', 'approver', 'branch']);

        return view('returns.show', ['return' => $return]);
    }
}
