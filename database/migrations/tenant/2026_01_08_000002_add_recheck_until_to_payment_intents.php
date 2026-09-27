<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Providers can report "failed" and later "completed" for the same
     * transaction. Until this time a failed intent keeps being re-checked.
     */
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->timestamp('recheck_until')->nullable()->after('completed_at');
            $table->timestamp('late_completed_at')->nullable()->after('recheck_until');
            $table->index(['status', 'recheck_until']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropIndex(['status', 'recheck_until']);
            $table->dropColumn(['recheck_until', 'late_completed_at']);
        });
    }
};
