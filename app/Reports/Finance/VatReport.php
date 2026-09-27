<?php

namespace App\Reports\Finance;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class VatReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'vat';
    }

    public function title(): string
    {
        return __('VAT report (TRA)');
    }

    public function description(): string
    {
        return __('Output VAT on sales vs input VAT on purchases for monthly TRA filing.');
    }

    public function icon(): string
    {
        return 'bi-bank';
    }

    public function group(): string
    {
        return __('Finance');
    }

    public function defaultPreset(): string
    {
        return 'last_month';
    }

    public function run(ReportFilters $f): ReportResult
    {
        $output = $this->items($f)->groupBy('sale_items.tax_rate')
            ->selectRaw('sale_items.tax_rate as rate, SUM(sale_items.line_total - sale_items.cart_discount_share - sale_items.tax_amount) as taxable, SUM(sale_items.tax_amount) as tax')->get();
        $returns = DB::table('sale_returns')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('created_at', [$f->from, $f->to])->sum('tax_total');
        $inputGrn = DB::table('goods_receipts')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('received_at', [$f->from, $f->to])
            ->selectRaw('COALESCE(SUM(subtotal),0) as taxable, COALESCE(SUM(tax_total),0) as tax')->first();
        $inputBills = DB::table('supplier_bills')->whereNull('goods_receipt_id')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('bill_date', [$f->from, $f->to])
            ->selectRaw('COALESCE(SUM(subtotal),0) as taxable, COALESCE(SUM(tax_total),0) as tax')->first();
        $purchaseReturns = DB::table('purchase_returns')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('created_at', [$f->from, $f->to])->sum('tax_total');

        $rows = [];
        foreach ($output as $o) {
            $rows[] = ['line' => __('Output VAT — sales at :r%', ['r' => (float) $o->rate]), 'taxable' => Money::round($o->taxable), 'tax' => Money::round($o->tax)];
        }
        $rows[] = ['line' => __('Less: VAT on customer returns'), 'taxable' => null, 'tax' => Money::negate($returns)];
        $outputTotal = Money::sub(Money::sum($output, 'tax'), $returns);
        $rows[] = ['line' => __('Total output VAT'), 'taxable' => Money::sum($output, 'taxable'), 'tax' => $outputTotal, 'bold' => true];
        $rows[] = ['line' => __('Input VAT — goods received'), 'taxable' => Money::round($inputGrn->taxable), 'tax' => Money::round($inputGrn->tax)];
        $rows[] = ['line' => __('Input VAT — other supplier bills'), 'taxable' => Money::round($inputBills->taxable), 'tax' => Money::round($inputBills->tax)];
        $rows[] = ['line' => __('Less: VAT on returns to suppliers'), 'taxable' => null, 'tax' => Money::negate($purchaseReturns)];
        $inputTotal = Money::sub(Money::add($inputGrn->tax, $inputBills->tax), $purchaseReturns);
        $rows[] = ['line' => __('Total input VAT'), 'taxable' => null, 'tax' => $inputTotal, 'bold' => true];
        $payable = Money::sub($outputTotal, $inputTotal);
        $rows[] = ['line' => Money::isNegative($payable) ? __('VAT refundable / carried forward') : __('Net VAT payable to TRA'), 'taxable' => null, 'tax' => $payable, 'bold' => true];

        return new ReportResult(
            columns: ['line' => ['label' => __('Line')], 'taxable' => ['label' => __('Taxable value'), 'type' => 'money'], 'tax' => ['label' => __('VAT'), 'type' => 'money']],
            rows: $rows,
            kpis: [
                ['label' => __('Output VAT'), 'value' => money($outputTotal), 'icon' => 'bi-arrow-up-right', 'color' => 'danger'],
                ['label' => __('Input VAT'), 'value' => money($inputTotal), 'icon' => 'bi-arrow-down-left', 'color' => 'success'],
                ['label' => __('Net VAT payable'), 'value' => money($payable), 'icon' => 'bi-bank', 'color' => 'primary'],
            ],
            note: __('VRN: :v · Figures assume selling prices :incl VAT. Verify with your tax consultant before filing.', ['v' => setting('business.vrn') ?: '—', 'incl' => setting('tax.prices_include_vat') ? __('include') : __('exclude')]),
        );
    }
}
