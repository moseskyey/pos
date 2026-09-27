<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Manager overrides: a restricted action is approved by entering the PIN of a
 * user who holds the required permission. Five failed attempts lock PIN entry
 * for the requesting user for 15 minutes.
 */
class ApprovalService
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 900;

    public function needsApproval(User $user, string $permission): bool
    {
        return ! $user->can($permission);
    }

    public function approverFor(string $pin, string $permission, User $requester, ?int $branchId = null): User
    {
        $key = 'pin-attempts:'.$requester->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([
                'pin' => __('Too many incorrect PINs. Try again in :minutes minutes.', ['minutes' => $minutes]),
            ]);
        }

        if (! preg_match('/^\d{4,6}$/', $pin)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw ValidationException::withMessages(['pin' => __('Enter a 4–6 digit PIN.')]);
        }

        $candidates = User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->when($branchId, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId))
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'owner'))
                ->orWhereHas('permissions', fn ($p) => $p->where('name', 'branches.view_all'))
                ->orWhereHas('roles.permissions', fn ($p) => $p->where('name', 'branches.view_all'))))
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, $candidate->pin) && $candidate->can($permission)) {
                RateLimiter::clear($key);

                return $candidate;
            }
        }

        RateLimiter::hit($key, self::LOCK_SECONDS);
        $left = RateLimiter::remaining($key, self::MAX_ATTEMPTS);

        throw ValidationException::withMessages([
            'pin' => $left > 0
                ? __('Invalid PIN or not authorised. :left attempts left.', ['left' => $left])
                : __('Too many incorrect PINs. PIN entry is locked for 15 minutes.'),
        ]);
    }

    public function record(string $action, User $requester, User $approver, ?Model $subject = null, mixed $amount = null, ?string $reason = null, ?int $branchId = null): Approval
    {
        $approval = Approval::create([
            'branch_id' => $branchId ?? $subject?->branch_id ?? null,
            'action' => $action,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'amount' => $amount,
            'reason' => $reason,
        ]);

        activity('approvals')
            ->causedBy($approver)
            ->performedOn($subject ?? $approval)
            ->withProperties(['action' => $action, 'requested_by' => $requester->name, 'amount' => $amount, 'reason' => $reason])
            ->log("Manager override approved: $action");

        return $approval;
    }
}
