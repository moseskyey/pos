<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Branch;
use App\Models\Register;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        return view('branches.index');
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->validated());
        $branch->registers()->create(['name' => __('Till 1'), 'code' => 'T1']);
        $request->user()->branches()->syncWithoutDetaching([$branch->id]);

        if ($request->boolean('save_new')) {
            return redirect()->route('branches.create')->with('success', __('Branch created.'));
        }

        return redirect()->route('branches.show', $branch)->with('success', __('Branch created.'));
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);
        $branch->load(['registers' => fn ($q) => $q->withoutGlobalScopes()->orderBy('name'), 'users.roles']);

        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('branches.form', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return redirect()->route('branches.show', $branch)->with('success', __('Branch updated.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        if (Branch::count() <= 1) {
            return back()->with('error', __('You cannot delete the only branch.'));
        }

        $branch->update(['is_active' => false]);
        $branch->delete();

        return redirect()->route('branches.index')->with('success', __('Branch archived.'));
    }

    public function storeRegister(RegisterRequest $request, Branch $branch): RedirectResponse
    {
        $branch->registers()->create($request->validated() + ['is_active' => true]);

        return back()->with('success', __('Register added.'));
    }

    public function updateRegister(RegisterRequest $request, Branch $branch, int $register): RedirectResponse
    {
        $register = Register::withoutGlobalScopes()->where('branch_id', $branch->id)->findOrFail($register);
        $register->update($request->validated());

        return back()->with('success', __('Register updated.'));
    }
}
