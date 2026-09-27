<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('number', 40)->unique();
            $table->string('reason');
            $table->string('refund_method', 30);
            $table->string('refund_reference')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('refund_total', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->string('condition', 20)->default('restock'); // restock | damaged
            $table->timestamps();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('number', 40)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['customer_payment_allocations', 'customer_payments', 'sale_return_items', 'sale_returns'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
