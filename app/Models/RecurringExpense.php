<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'expense_category_id', 'description', 'amount', 'payment_method', 'frequency', 'next_run_date', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'next_run_date' => 'date', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
