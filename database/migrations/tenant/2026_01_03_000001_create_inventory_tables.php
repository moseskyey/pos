<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'product_id']);
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 64);
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['product_id', 'branch_id', 'expiry_date']);
            $table->index(['branch_id', 'expiry_date']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 3)->default(0);
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('number', 40)->unique();
            $table->string('reason', 30);
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('direction', 3); // in | out
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('batch_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->string('status', 20)->default('requested');
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['from_branch_id', 'status']);
            $table->index(['to_branch_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity_requested', 15, 3);
            $table->decimal('quantity_dispatched', 15, 3)->nullable();
            $table->decimal('quantity_received', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('number', 40)->unique();
            $table->string('scope', 20)->default('full');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('counting');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('expected_quantity', 15, 3)->default(0);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->unique(['stock_take_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['stock_take_items', 'stock_takes', 'stock_transfer_items', 'stock_transfers', 'stock_adjustment_items', 'stock_adjustments', 'stock_movements', 'product_batches', 'product_stocks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
