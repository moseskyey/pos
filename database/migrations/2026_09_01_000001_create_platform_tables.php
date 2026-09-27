<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (platform) tables: businesses, plans, billing and platform admins.
 * Each business's own data lives in its own database (database/migrations/tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedSmallInteger('interval_months')->default(1);
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database')->nullable();
            $table->string('storage_folder')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable()->index();
            $table->string('owner_phone', 20)->nullable()->index();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // DATETIME, not TIMESTAMP: prepaid periods can run past 2038.
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('paid_until')->nullable()->index();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_super')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->nullable()->unique();
            $table->string('reference', 60)->unique();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('months')->default(1);
            $table->string('method', 30);
            $table->string('status', 20)->default('pending')->index();
            $table->string('phone', 20)->nullable();
            $table->string('provider_reference', 100)->nullable()->index();
            $table->string('message')->nullable();
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('recheck_until')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('result', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('level', 20)->default('info');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('subscription_callbacks');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('tenant_logins');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
