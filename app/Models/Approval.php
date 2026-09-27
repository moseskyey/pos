<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $fillable = ['branch_id', 'action', 'requested_by', 'approved_by', 'subject_type', 'subject_id', 'amount', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
