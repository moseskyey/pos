<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credit terms: payment days per customer (null = business default) and a
     * due date on every credit sale, so aging and reminders use the real due date.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_days')->nullable()->after('credit_limit');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('balance_due');
            $table->index(['customer_id', 'due_date']);
        });

        // Existing unpaid credit sales were always due 30 days after the sale.
        DB::table('sales')->whereNotNull('customer_id')->where('balance_due', '>', 0)->whereNull('due_date')
            ->orderBy('id')->select(['id', 'created_at'])
            ->chunkById(500, function ($sales) {
                foreach ($sales as $sale) {
                    DB::table('sales')->where('id', $sale->id)
                        ->update(['due_date' => Carbon::parse($sale->created_at)->addDays(30)->toDateString()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'due_date']);
            $table->dropColumn('due_date');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_days');
        });
    }
};
