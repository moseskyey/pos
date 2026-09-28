<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales commission and targets, and API tokens for integrations
     * (mobile apps, online shops, accounting tools).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('pin');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('salesperson_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['salesperson_id', 'created_at']);
        });

        // Monthly sales targets: per salesperson (user_id) or for a whole branch (user_id null).
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('month', 7); // YYYY-MM
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['branch_id', 'user_id', 'month']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->char('token_hash', 64)->unique(); // sha256 of the secret part
            $table->string('prefix', 20); // shown in the list to tell tokens apart
            $table->json('abilities'); // ["read"] or ["read","write"]
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('sales_targets');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['salesperson_id', 'created_at']);
            $table->dropConstrainedForeignId('salesperson_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('commission_rate'));
    }
};
