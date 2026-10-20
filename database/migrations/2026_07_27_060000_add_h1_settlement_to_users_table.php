<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('pending_xp')->default(0)->after('daily_xp_earned');
            $table->date('last_settlement_date')->nullable()->after('pending_xp');
            $table->timestamp('xp_multiplier_expires_at')->nullable()->after('xp_multiplier');
            $table->date('daily_clear_achieved_at')->nullable()->after('daily_clear_streak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pending_xp',
                'last_settlement_date',
                'xp_multiplier_expires_at',
                'daily_clear_achieved_at',
            ]);
        });
    }
};
