<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers per branch per type, e.g. INV-DSM01-000123.
 * Must be called inside the transaction that creates the document.
 */
class DocumentNumberService
{
    public const TYPES = [
        'invoice', 'purchase_order', 'goods_receipt', 'return', 'transfer', 'quotation', 'expense',
        'adjustment', 'stock_take', 'shift', 'customer_payment', 'supplier_payment', 'purchase_return', 'layaway',
    ];

    public function next(string $type, Branch|int $branch): string
    {
        $branch = $branch instanceof Branch ? $branch : Branch::withTrashed()->findOrFail($branch);

        return DB::transaction(function () use ($type, $branch) {
            $sequence = DocumentSequence::query()
                ->where('branch_id', $branch->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create(['branch_id' => $branch->id, 'type' => $type, 'next_number' => 1]);
                $sequence = DocumentSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
            }

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return $this->format($type, $branch->code, $number);
        });
    }

    public function format(string $type, string $branchCode, int $number): string
    {
        $prefix = setting("prefix.$type", strtoupper(substr($type, 0, 3)));

        return sprintf('%s-%s-%06d', $prefix, strtoupper($branchCode), $number);
    }
}
