<?php

namespace App\Models;

use App\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Expense extends Model
{
    use BelongsToBranch, LogsActivity, SoftDeletes;

    protected $fillable = [
        'branch_id', 'expense_category_id', 'recurring_expense_id', 'shift_id', 'number', 'expense_date', 'amount', 'payment_method',
        'paid_from_drawer', 'reference', 'payee', 'description', 'attachment_path', 'user_id',
    ];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'paid_from_drawer' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['amount', 'expense_category_id', 'expense_date', 'payment_method', 'description'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? route('files.show', ['path' => $this->attachment_path]) : null;
    }
}
