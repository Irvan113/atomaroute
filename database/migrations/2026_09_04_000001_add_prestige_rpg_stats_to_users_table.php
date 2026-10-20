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
            // Prestige / Ascension System
            $table->unsignedInteger('prestige_tier')->default(1)->after('level');     // Tier 1-5
            $table->unsignedInteger('prestige_level')->default(1)->after('prestige_tier');  // Level 1-50
            $table->unsignedInteger('prestige_xp')->default(0)->after('prestige_level');    // XP within current level
            $table->unsignedBigInteger('lifetime_xp')->default(0)->after('prestige_xp');    // Total XP ever earned

            // RPG Productivity Stats (0-100 scale)
            $table->unsignedInteger('stat_focus')->default(0)->after('lifetime_xp');
            $table->unsignedInteger('stat_consistency')->default(0)->after('stat_focus');
            $table->unsignedInteger('stat_execution')->default(0)->after('stat_consistency');
            $table->unsignedInteger('stat_organization')->default(0)->after('stat_execution');

            // Seasonal System
            $table->unsignedInteger('seasonal_xp')->default(0)->after('stat_organization');
            $table->unsignedInteger('seasonal_level')->default(1)->after('seasonal_xp');
            $table->string('current_season')->nullable()->after('seasonal_level');

            // Legacy
            $table->date('member_since')->nullable()->after('current_season');
            $table->unsignedInteger('total_tasks_completed')->default(0)->after('member_since');
        });

        // Backfill: set member_since from created_at, lifetime_xp from xp
        \Illuminate\Support\Facades\DB::statement('UPDATE users SET lifetime_xp = COALESCE(xp, 0), member_since = DATE(created_at), total_tasks_completed = 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'prestige_tier', 'prestige_level', 'prestige_xp', 'lifetime_xp',
                'stat_focus', 'stat_consistency', 'stat_execution', 'stat_organization',
                'seasonal_xp', 'seasonal_level', 'current_season',
                'member_since', 'total_tasks_completed',
            ]);
        });
    }
};
