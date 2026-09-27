<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('type', 20)->default('retail');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('store_credit', 15, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('number', 40)->unique();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 15, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('over_short', 15, 2)->nullable();
            $table->json('denominations')->nullable();
            $table->json('summary')->nullable();
            $table->boolean('force_closed')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'opened_at']);
            $table->index(['user_id', 'status']);
            $table->index(['register_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('shift_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 10); // in | out
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->nullableMorphs('reference');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('register_id')->nullable()->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('number', 40)->nullable()->unique();
            $table->string('status', 20);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('rounding', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->decimal('tendered', 15, 2)->default(0);
            $table->decimal('change_due', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('cart_discount_type', 10)->nullable();
            $table->decimal('cart_discount_value', 15, 2)->nullable();
            $table->string('hold_note')->nullable();
            $table->text('note')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->date('valid_until')->nullable();
            $table->foreignId('converted_sale_id')->nullable()->constrained('sales');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason')->nullable();
            $table->unsignedInteger('reprint_count')->default(0);
            $table->string('fiscal_code')->nullable();
            $table->text('fiscal_qr')->nullable();
            $table->integer('loyalty_earned')->default(0);
            $table->integer('loyalty_redeemed')->default(0);
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('shift_id');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->string('unit_name', 30)->nullable();
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->decimal('quantity', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('list_price', 15, 2);
            $table->string('price_tier', 20)->default('retail');
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('cart_discount_share', 15, 2)->default(0);
            $table->string('tax_type', 20)->default('standard');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->index('product_id');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->string('method', 30);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_status', 20)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['method', 'created_at']);
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->string('account', 20)->default('receivable'); // receivable | store_credit
            $table->string('type', 30);
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->date('due_date')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->string('type', 20); // earn | redeem | reverse | adjust
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['loyalty_transactions', 'customer_ledger_entries', 'sale_payments', 'sale_items', 'sales', 'cash_movements', 'shifts', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
