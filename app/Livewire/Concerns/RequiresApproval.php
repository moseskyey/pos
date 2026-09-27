<?php

namespace App\Livewire\Concerns;

use App\Services\ApprovalService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Manager PIN approval flow for Livewire components.
 * Call requestApproval(); on a valid PIN the approver id is stored in
 * $approvals[action] and the continuation method is called again.
 */
trait RequiresApproval
{
    #[Locked]
    public array $approvals = [];

    #[Locked]
    public ?array $approval = null;

    public string $approvalPin = '';

    public string $approvalReason = '';

    public function requestApproval(string $action, string $permission, string $message, string $then, array $args = [], bool $needsReason = false): void
    {
        $this->approval = compact('action', 'permission', 'message', 'then', 'args') + ['needs_reason' => $needsReason];
        $this->approvalPin = '';
        $this->approvalReason = '';
        $this->resetErrorBag(['pin', 'reason']);
    }

    public function submitApproval(ApprovalService $service): void
    {
        if (! $this->approval) {
            return;
        }
        if (! empty($this->approval['needs_reason']) && trim($this->approvalReason) === '') {
            $this->addError('reason', __('Enter a reason.'));

            return;
        }

        try {
            $approver = $service->approverFor($this->approvalPin, $this->approval['permission'], auth()->user(), $this->approvalBranchId());
        } catch (ValidationException $e) {
            $this->approvalPin = '';
            $this->addError('pin', $e->validator->errors()->first('pin'));

            return;
        }

        $this->approvals[$this->approval['action']] = $approver->id;
        $then = $this->approval['then'];
        $args = $this->approval['args'];
        $reason = $this->approvalReason;
        $this->approval = null;
        $this->approvalPin = '';
        $this->dispatch('toast', message: __('Approved by :name', ['name' => $approver->name]), type: 'success');

        if ($then && method_exists($this, $then)) {
            app()->call([$this, $then], $reason !== '' ? [...$args, $reason] : $args);
        }
    }

    public function cancelApproval(): void
    {
        $this->approval = null;
        $this->approvalPin = '';
    }

    protected function approvalBranchId(): ?int
    {
        return branch_context()->currentId();
    }
}
