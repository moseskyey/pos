<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves which branches the current user may see and which one is selected
 * in the navbar branch switcher. A null current branch means "All branches"
 * (only available to users with `branches.view_all`).
 */
class BranchContext
{
    public const SESSION_KEY = 'current_branch_id';

    protected ?Collection $accessible = null;

    protected ?int $accessibleFor = null;

    protected ?int $forcedBranchId = null;

    protected bool $bypass = false;

    public function user(): ?User
    {
        return Auth::user();
    }

    public function canViewAll(): bool
    {
        return (bool) $this->user()?->can('branches.view_all');
    }

    /** @return Collection<int, Branch> */
    public function accessibleBranches(): Collection
    {
        $user = $this->user();
        if (! $user) {
            return collect();
        }
        // Memoised per user: one app instance can serve several users (queue workers, tests).
        if ($this->accessible !== null && $this->accessibleFor === $user->id) {
            return $this->accessible;
        }
        $this->accessibleFor = $user->id;

        $query = Branch::query()->where('is_active', true)->orderBy('name');
        if (! $this->canViewAll()) {
            $query->whereIn('id', $user->branches()->pluck('branches.id'));
        }

        return $this->accessible = $query->get();
    }

    /** @return array<int> */
    public function accessibleIds(): array
    {
        return $this->accessibleBranches()->pluck('id')->all();
    }

    public function currentId(): ?int
    {
        if ($this->forcedBranchId !== null) {
            return $this->forcedBranchId;
        }

        $ids = $this->accessibleIds();
        $selected = session(self::SESSION_KEY);

        if ($selected === 'all' && $this->canViewAll()) {
            return null;
        }
        if ($selected && in_array((int) $selected, $ids, true)) {
            return (int) $selected;
        }

        $default = $this->user()?->default_branch_id;
        if ($default && in_array($default, $ids, true)) {
            return $default;
        }

        return $ids[0] ?? null;
    }

    public function current(): ?Branch
    {
        $id = $this->currentId();

        return $id ? $this->accessibleBranches()->firstWhere('id', $id) ?? Branch::find($id) : null;
    }

    public function isAll(): bool
    {
        return $this->currentId() === null && $this->canViewAll();
    }

    /** Branch ids the current view is filtered to. */
    public function activeIds(): array
    {
        $current = $this->currentId();

        return $current ? [$current] : $this->accessibleIds();
    }

    public function switch(int|string $branchId): bool
    {
        if ($branchId === 'all') {
            if (! $this->canViewAll()) {
                return false;
            }
            session([self::SESSION_KEY => 'all']);

            return true;
        }
        if (! in_array((int) $branchId, $this->accessibleIds(), true)) {
            return false;
        }
        session([self::SESSION_KEY => (int) $branchId]);

        return true;
    }

    public function canAccess(?int $branchId): bool
    {
        return $branchId !== null && in_array($branchId, $this->accessibleIds(), true);
    }

    /** Run a callback scoped to a specific branch (used by jobs and services). */
    public function forBranch(int $branchId, callable $callback): mixed
    {
        $previous = $this->forcedBranchId;
        $this->forcedBranchId = $branchId;
        try {
            return $callback();
        } finally {
            $this->forcedBranchId = $previous;
        }
    }

    /** Run a callback with branch scoping disabled. */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;
        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }

    public function scopingEnabled(): bool
    {
        return ! $this->bypass && $this->user() !== null;
    }

    public function reset(): void
    {
        $this->accessible = null;
    }
}
