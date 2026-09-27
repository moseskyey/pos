<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->string('reference', 64)->unique();
            $table->string('gateway', 30);
            $table->string('method', 30);
            $table->string('phone', 20);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending'); // pending | processing | completed | failed
            $table->string('provider_reference')->nullable();
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('status_checks')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('provider_reference');
        });

        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('reference', 64)->nullable()->index();
            $table->boolean('signature_valid')->default(false);
            $table->string('ip', 45)->nullable();
            $table->json('payload')->nullable();
            $table->string('result', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payment_intents');
    }
};
