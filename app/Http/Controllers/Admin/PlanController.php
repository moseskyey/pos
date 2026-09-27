<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\Plan;
use App\Models\Platform\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index');
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new Plan(['interval_months' => 1, 'is_active' => true, 'sort_order' => Plan::max('sort_order') + 1])]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($request->validated());
        AdminActivity::record('plan.created', "Created plan {$plan->name}", null, $request->validated());

        return redirect()->route($request->boolean('save_new') ? 'admin.plans.create' : 'admin.plans.index')->with('success', __('Plan created.'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan->loadCount('tenants')]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->fill($request->validated());
        $changes = $plan->getDirty();
        $plan->save();
        AdminActivity::record('plan.updated', "Updated plan {$plan->name}", null, $changes);

        return redirect()->route('admin.plans.index')->with('success', __('Plan updated.'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->tenants()->withTrashed()->exists() || SubscriptionPayment::where('plan_id', $plan->id)->exists()) {
            $plan->update(['is_active' => false]);

            return redirect()->route('admin.plans.index')->with('success', __('The plan is in use, so it was deactivated instead. Existing businesses keep it.'));
        }
        $plan->delete();
        AdminActivity::record('plan.deleted', "Deleted plan {$plan->name}");

        return redirect()->route('admin.plans.index')->with('success', __('Plan deleted.'));
    }
}
