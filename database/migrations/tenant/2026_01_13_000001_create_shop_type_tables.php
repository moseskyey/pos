<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop-type features: serial/IMEI numbers with warranty (electronics),
     * pharmacy fields and prescriptions, cheques, and supplier ↔ product links.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_serials')->default(false)->after('track_batches');
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('track_serials');
            $table->string('generic_name', 160)->nullable()->after('name');
            $table->string('strength', 60)->nullable()->after('generic_name');
            $table->string('dosage_form', 40)->nullable()->after('strength');
            $table->boolean('requires_prescription')->default(false)->after('dosage_form');
            $table->index('generic_name');
        });

        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->string('serial', 64);
            $table->string('status', 20)->default('in_stock'); // in_stock | sold | defective
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'serial']);
            $table->index('serial');
            $table->index(['branch_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('prescription_ref', 60)->nullable()->after('note');
            $table->string('prescriber', 120)->nullable()->after('prescription_ref');
        });

        // Cheques received from customers or issued to suppliers, tracked until they clear.
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('direction', 10); // received | issued
            $table->string('number', 40);
            $table->string('bank', 80)->nullable();
            $table->date('cheque_date');
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending'); // pending | cleared | bounced | cancelled
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('payable'); // sale_payment | customer_payment | supplier_payment
            $table->timestamp('status_at')->nullable();
            $table->foreignId('status_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'cheque_date']);
        });

        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku', 64)->nullable();
            $table->decimal('last_cost', 15, 2)->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'supplier_id']);
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('cheques');
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['prescription_ref', 'prescriber']));
        Schema::dropIfExists('product_serials');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['generic_name']);
            $table->dropColumn(['track_serials', 'warranty_months', 'generic_name', 'strength', 'dosage_form', 'requires_prescription']);
        });
    }
};
