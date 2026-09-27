<?php

namespace App\Http\Controllers;

use App\Enums\TaxType;
use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\ProductService;
use App\Support\BranchContext;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(protected ProductService $products) {}

    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        return view('products.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);
        $product = new Product([
            'tax_type' => TaxType::Standard, 'track_stock' => true, 'is_active' => true,
            'unit_id' => Unit::where('short_name', 'pc')->value('id'),
            'category_id' => $request->integer('category_id') ?: null,
        ]);

        if ($request->filled('copy')) {
            $source = Product::with(['barcodes', 'units'])->findOrFail($request->integer('copy'));
            $product = $source->replicate(['sku', 'image_path']);
            $product->name = $source->name.' ('.__('copy').')';
        }

        return view('products.form', $this->formData($product));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated(), $request->user(), $request->file('image'));

        if ($request->boolean('save_new')) {
            return redirect()->route('products.create', ['category_id' => $product->category_id])->with('success', __('Product ":name" created.', ['name' => $product->name]));
        }

        return redirect()->route('products.show', $product)->with('success', __('Product created.'));
    }

    public function show(Product $product, BranchContext $context): View
    {
        $this->authorize('view', $product);
        $product->load(['category', 'brand', 'unit', 'barcodes.productUnit.unit', 'units.unit', 'parent',
            'variants' => fn ($q) => $q->withSum(['stocks as stock_qty' => fn ($s) => $s->whereIn('branch_id', $context->activeIds())], 'quantity')]);

        $stocks = class_exists(ProductStock::class)
            ? ProductStock::query()->withoutGlobalScopes()->with('branch')->where('product_id', $product->id)
                ->whereIn('branch_id', $context->accessibleIds())->get()
            : collect();

        $batches = class_exists(ProductBatch::class)
            ? ProductBatch::query()->with('branch')->where('product_id', $product->id)->where('quantity', '>', 0)->orderBy('expiry_date')->get()
            : collect();

        $salesChart = null;
        if (class_exists(SaleItem::class)) {
            $rows = SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sale_items.product_id', $product->id)
                ->where('sales.status', 'completed')
                ->whereIn('sales.branch_id', $context->activeIds())
                ->where('sales.created_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw(Sql::date('sales.created_at')))
                ->selectRaw(Sql::date('sales.created_at').' as d, SUM(sale_items.base_quantity) as qty, SUM(sale_items.line_total) as total')
                ->pluck('qty', 'd');
            $labels = [];
            $data = [];
            for ($i = 29; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $labels[] = $day->format('d M');
                $data[] = (float) ($rows[$day->toDateString()] ?? 0);
            }
            $salesChart = ['type' => 'bar', 'money' => false, 'labels' => $labels, 'datasets' => [['label' => __('Units sold'), 'data' => $data]]];
        }

        $priceHistory = $product->priceHistories()->with('user')->limit(20)->get();

        return view('products.show', compact('product', 'stocks', 'batches', 'salesChart', 'priceHistory'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);
        $product->load(['barcodes', 'units.barcodes', 'variants.barcodes']);

        return view('products.form', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->validated(), $request->user(), $request->file('image'), $request->input('price_reason'));

        return redirect()->route('products.show', $product)->with('success', __('Product updated.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->update(['is_active' => false]);
        $product->variants()->update(['is_active' => false]);
        $product->variants()->delete();
        $product->delete();

        return redirect()->route('products.index')->with('success', __('Product archived.'));
    }

    protected function formData(Product $product): array
    {
        $units = Unit::query()->where('is_active', true)->orderBy('name')->get();

        return [
            'product' => $product,
            'categories' => Category::options(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'units' => $units->mapWithKeys(fn ($u) => [$u->id => $u->label()]),
            'conversions' => UnitConversion::query()->get(['from_unit_id', 'to_unit_id', 'factor']),
            'taxTypes' => TaxType::options(),
        ];
    }
}
