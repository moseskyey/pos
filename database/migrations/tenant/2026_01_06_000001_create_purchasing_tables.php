<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('vrn', 30)->nullable();
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
            $table->index('phone');
        });

        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->string('type', 30);
            $table->decimal('debit', 15, 2)->default(0);  // reduces what we owe (payment, return)
            $table->decimal('credit', 15, 2)->default(0); // increases what we owe (bill, opening)
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['supplier_id', 'created_at']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('number', 40)->unique();
            $table->string('status', 20)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained();
            $table->string('number', 40)->unique();
            $table->string('supplier_invoice_no', 64)->nullable();
            $table->date('received_at');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['supplier_id', 'received_at']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->string('batch_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('goods_receipt_id')->nullable()->constrained();
            $table->string('bill_no', 64)->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
            $table->index(['branch_id', 'bill_date']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->string('number', 40)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->index(['branch_id', 'paid_at']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('goods_receipt_id')->nullable()->constrained();
            $table->string('number', 40)->unique();
            $table->string('reason');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30)->default('bank');
            $table->string('frequency', 20)->default('monthly'); // monthly | weekly
            $table->date('next_run_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->string('number', 40)->unique();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30);
            $table->boolean('paid_from_drawer')->default(false);
            $table->string('reference')->nullable();
            $table->string('payee')->nullable();
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'expense_date']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'recurring_expenses', 'expense_categories', 'purchase_return_items', 'purchase_returns', 'supplier_payment_allocations', 'supplier_payments',
            'supplier_bills', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'supplier_ledger_entries', 'suppliers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
