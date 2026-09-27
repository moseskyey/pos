<?php

namespace App\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, BranchContext $context): RedirectResponse
    {
        $request->validate(['branch_id' => ['required']]);

        if (! $context->switch($request->input('branch_id'))) {
            return back()->with('error', __('You do not have access to that branch.'));
        }
        $context->reset();

        return back()->with('success', __('Switched to :branch.', ['branch' => $context->current()?->name ?? __('All branches')]));
    }
}
