<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiListRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\BranchContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ApiListRequest $request, BranchContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);
        $filters = $request->validated();
        $ids = $context->activeIds();

        $products = Product::query()->with(['barcodes', 'category', 'brand', 'unit'])
            ->withSum(['stocks as stock_qty' => fn ($q) => $q->withoutGlobalScopes()->whereIn('branch_id', $ids)], 'quantity')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->search($term))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->paginate($request->perPage())->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product, BranchContext $context): ProductResource
    {
        $this->authorize('view', $product);
        $product->load(['barcodes', 'category', 'brand', 'unit'])
            ->loadSum(['stocks as stock_qty' => fn ($q) => $q->withoutGlobalScopes()->whereIn('branch_id', $context->activeIds())], 'quantity');

        return new ProductResource($product);
    }
}
