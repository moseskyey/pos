<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selling features: per-branch prices, promotions, product bundles (kits)
     * and gift cards / vouchers. Each is switched on per business in Settings → Features.
     */
    public function up(): void
    {
        // Per-branch selling prices; a row overrides the product/unit price at that branch.
        Schema::create('branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('retail_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'product_id', 'product_unit_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 20); // percent | amount | buy_get | multi_price
            $table->decimal('value', 15, 2)->default(0); // % off, TSh off per unit, or the N-for price
            $table->decimal('buy_qty', 15, 3)->nullable(); // buy_get: buy X; multi_price: N items
            $table->decimal('get_qty', 15, 3)->nullable(); // buy_get: get Y free
            $table->decimal('min_qty', 15, 3)->nullable();
            $table->string('applies_to', 20)->default('all'); // all | categories | products
            $table->json('branch_ids')->nullable(); // null = every branch
            $table->json('days_of_week')->nullable(); // ISO 1..7, null = every day
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index(['product_id']);
            $table->index(['category_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('cart_discount_share')->constrained()->nullOnDelete();
            $table->string('promotion_name', 120)->nullable()->after('promotion_id');
            $table->decimal('promo_discount', 15, 2)->default(0)->after('promotion_name');
            $table->json('bundle_components')->nullable()->after('line_total');
        });

        // Bundles / kits: one product that issues its components' stock.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('has_variants');
        });
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products');
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
            $table->unique(['bundle_id', 'component_id']);
        });

        // Gift cards & vouchers: a stored balance redeemed as a payment method.
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('kind', 20)->default('gift_card'); // gift_card (paid for) | voucher (complimentary)
            $table->decimal('initial_value', 15, 2);
            $table->decimal('balance', 15, 2);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->date('expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->string('type', 20); // issue | redeem | void | deactivate
            $table->decimal('amount', 15, 2); // signed: + adds to the balance, − spends it
            $table->decimal('balance_after', 15, 2);
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['gift_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('bundle_items');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_bundle'));
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_name', 'promo_discount', 'bundle_components']);
        });
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('branch_prices');
    }
};
