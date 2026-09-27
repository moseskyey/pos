<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchBranchRequest;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;

class BranchSwitchController extends Controller
{
    public function __invoke(SwitchBranchRequest $request, BranchContext $context): RedirectResponse
    {
        if (! $context->switch($request->input('branch_id'))) {
            return back()->with('error', __('You do not have access to that branch.'));
        }
        $context->reset();

        return back()->with('success', __('Switched to :branch.', ['branch' => $context->current()?->name ?? __('All branches')]));
    }
}
