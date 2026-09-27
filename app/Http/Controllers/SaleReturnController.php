<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SaleReturn::class);

        return view('returns.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SaleReturn::class);

        return view('returns.create', ['saleId' => $request->integer('sale') ?: null]);
    }

    public function show(Request $request, SaleReturn $return): View
    {
        $this->authorize('view', $return);
        $return->load(['items.product', 'sale', 'customer', 'user', 'approver', 'branch']);

        return view('returns.show', ['return' => $return]);
    }
}
