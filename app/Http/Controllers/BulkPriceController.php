<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkPriceRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\BulkPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkPriceController extends Controller
{
    public function __construct(protected BulkPriceService $service) {}

    public function create(Request $request): View
    {
        $this->authorize('products.edit_price');
        $filters = $request->only(['category_id', 'brand_id', 'field', 'mode', 'value', 'rounding']);
        $sample = collect();
        $count = 0;
        if ($request->filled('value')) {
            $query = $this->service->query($filters);
            $count = (clone $query)->count();
            $field = ($filters['field'] ?? 'retail_price') === 'both' ? 'retail_price' : ($filters['field'] ?? 'retail_price');
            $sample = $query->orderBy('name')->limit(10)->get()->map(fn ($p) => [
                'name' => $p->name,
                'old' => $p->{$field},
                'new' => $p->{$field} !== null ? $this->service->newPrice((string) $p->{$field}, $filters['mode'] ?? 'percent', (string) $filters['value'], (int) ($filters['rounding'] ?? 0)) : null,
            ]);
        }

        return view('products.bulk-price', [
            'categories' => Category::options(),
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
            'sample' => $sample,
            'count' => $count,
        ]);
    }

    public function store(BulkPriceRequest $request): RedirectResponse
    {
        $this->authorize('products.edit_price');
        $data = $request->validated();

        if (empty($data['category_id']) && empty($data['brand_id']) && ! $request->boolean('confirm_all')) {
            return back()->withInput()->with('error', __('Select a category or brand, or confirm updating all products.'));
        }

        $count = $this->service->apply($data, $request->user());

        return redirect()->route('products.index')->with('success', __(':n product prices updated.', ['n' => $count]));
    }
}
