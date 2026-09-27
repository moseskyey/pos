<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/*
 * Regression for the bug-scan report #4: BranchPolicy lazy-loaded
 * $user->branches, which throws a LazyLoadingViolationException in
 * non-production mode when the user came from a multi-row query.
 */
it('checks branch access without lazy loading', function () {
    $branch = Branch::factory()->create();
    $manager = actingAsRole('manager', $branch);
    $other = User::factory()->create();

    Model::preventLazyLoading();
    $fromCollection = User::query()->whereIn('id', [$manager->id, $other->id])->orderBy('id')->get()->first();

    expect($fromCollection->can('view', $branch))->toBeTrue()
        ->and($fromCollection->can('view', Branch::factory()->create()))->toBeFalse();
    $this->get(route('branches.show', $branch))->assertOk();
});
