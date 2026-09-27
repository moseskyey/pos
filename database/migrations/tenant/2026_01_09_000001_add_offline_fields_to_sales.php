<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales made while the till was offline are recorded when it reconnects.
     * Anything that would normally have needed a manager is kept as a review flag.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('completed_at');
            $table->json('review_flags')->nullable()->after('synced_at');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['synced_at']);
            $table->dropColumn(['synced_at', 'review_flags']);
        });
    }
};
