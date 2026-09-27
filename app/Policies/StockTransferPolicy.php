<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;

/** Transfers belong to two branches: each step is checked against the branch doing it. */
class StockTransferPolicy extends BasePolicy
{
    protected string $view = 'stock.transfer';

    protected string $manage = 'stock.transfer';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['stock.transfer', 'stock.transfer.approve']);
    }

    public function view(User $user, Model $transfer): bool
    {
        return $user->canAny(['stock.transfer', 'stock.transfer.approve', 'stock.view']) && $this->involves($transfer);
    }

    public function approve(User $user, Model $transfer): bool
    {
        return $user->can('stock.transfer.approve') && $this->involves($transfer);
    }

    public function dispatch(User $user, Model $transfer): bool
    {
        /** @var StockTransfer $transfer */
        return $user->can('stock.transfer') && app(BranchContext::class)->canAccess($transfer->from_branch_id);
    }

    public function receive(User $user, Model $transfer): bool
    {
        /** @var StockTransfer $transfer */
        return $user->can('stock.transfer') && app(BranchContext::class)->canAccess($transfer->to_branch_id);
    }

    public function cancel(User $user, Model $transfer): bool
    {
        return $user->canAny(['stock.transfer', 'stock.transfer.approve']) && $this->involves($transfer);
    }

    protected function involves(Model $transfer): bool
    {
        /** @var StockTransfer $transfer */
        $context = app(BranchContext::class);

        return $context->canAccess($transfer->from_branch_id) || $context->canAccess($transfer->to_branch_id);
    }
}
