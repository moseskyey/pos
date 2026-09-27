<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\RegisterSerialsRequest;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\SerialService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SerialController extends Controller
{
    public function __construct(protected SerialService $serials) {}

    /** Serial list plus warranty look-up (?q=IMEI). */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductSerial::class);
        $query = trim((string) $request->query('q', ''));

        return view('serials.index', [
            'query' => $query,
            'found' => $query !== '' ? $this->serials->find($query) : null,
            'products' => $request->user()->can('create', ProductSerial::class)
                ? Product::query()->where('track_serials', true)->where('is_active', true)->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }

    public function store(RegisterSerialsRequest $request, BranchContext $context): RedirectResponse
    {
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        try {
            $created = $this->serials->register(Product::findOrFail($request->integer('product_id')), $branchId, SerialService::parse($request->input('serials')), $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', trans_choice(':count serial number recorded.|:count serial numbers recorded.', $created->count()));
    }
}
