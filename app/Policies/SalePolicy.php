<?php

namespace App\Policies;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Sales and quotations (both are Sale rows). Cashiers with sales.view only
 * see their own sales; sales.view_all sees every sale in their branches.
 */
class SalePolicy extends BasePolicy
{
    protected string $view = 'sales.view';

    protected string $manage = 'sales.create';

    public function viewAny(User $user): bool
    {
        return $user->canAny(['sales.view', 'sales.view_all']);
    }

    public function view(User $user, Model $sale): bool
    {
        /** @var Sale $sale */
        return ($user->can('sales.view_all') || ($user->can('sales.view') && $sale->user_id === $user->id)) && $this->inBranch($sale);
    }

    /** Invoices and delivery notes. */
    public function document(User $user, Model $sale): bool
    {
        return $user->canAny(['sales.view', 'sales.view_all']) && $this->inBranch($sale);
    }

    public function reprint(User $user, Model $sale): bool
    {
        return $user->canAny(['sales.reprint', 'sales.view_all']) && $this->inBranch($sale);
    }

    public function manageQuotations(User $user): bool
    {
        return $user->can('quotations.manage');
    }

    public function viewQuotation(User $user, Model $sale): bool
    {
        return $user->can('quotations.manage') && $this->inBranch($sale);
    }

    public function updateQuotation(User $user, Model $sale): bool
    {
        /** @var Sale $sale */
        return $user->can('quotations.manage') && $sale->status === SaleStatus::Quotation && $this->inBranch($sale);
    }

    public function convertQuotation(User $user, Model $sale): bool
    {
        /** @var Sale $sale */
        return $user->can('pos.access') && $sale->status === SaleStatus::Quotation && $this->inBranch($sale);
    }
}
