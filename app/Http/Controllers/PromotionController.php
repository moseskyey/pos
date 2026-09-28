<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(protected PromotionService $promotions) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Promotion::class);

        return view('promotions.index', ['running' => $this->promotions->running(branch_context()->currentId())->count()]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Promotion::class);

        return view('promotions.form', $this->formData(new Promotion(['type' => 'percent', 'applies_to' => 'all', 'is_active' => true])));
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        $promotion = $this->promotions->save($request->promotionData(), $request->user());

        return $request->boolean('save_new')
            ? redirect()->route('promotions.create')->with('success', __('Promotion created.'))
            : redirect()->route('promotions.index')->with('success', __('Promotion ":n" created.', ['n' => $promotion->name]));
    }

    public function edit(Request $request, Promotion $promotion): View
    {
        $this->authorize('update', $promotion);

        return view('promotions.form', $this->formData($promotion->load('targets')));
    }

    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->promotions->save($request->promotionData(), $request->user(), $promotion);

        return redirect()->route('promotions.index')->with('success', __('Promotion updated.'));
    }

    public function toggle(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', $promotion->is_active ? __('Promotion switched on.') : __('Promotion switched off.'));
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);
        $promotion->delete();

        return redirect()->route('promotions.index')->with('success', __('Promotion deleted.'));
    }

    protected function formData(Promotion $promotion): array
    {
        $selected = $promotion->targets->pluck('product_id')->filter()->all();

        return [
            'promotion' => $promotion,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::options(),
            // Current selections first so they show even outside the first 500.
            'products' => Product::query()->where('has_variants', false)->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selected))
                ->orderByRaw($selected ? 'CASE WHEN id IN ('.implode(',', array_map('intval', $selected)).') THEN 0 ELSE 1 END' : '1')
                ->orderBy('name')->limit(500)->get(['id', 'name', 'sku'])
                ->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->sku ? ' · '.$p->sku : '')]),
        ];
    }
}
