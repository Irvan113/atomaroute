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
            $table->boolean('share_profile_with_friends')->default(true)->after('league_season_claimed');
            $table->boolean('share_daily_tasks')->default(true)->after('share_profile_with_friends');
            $table->boolean('share_achievements')->default(true)->after('share_daily_tasks');
            $table->boolean('allow_friend_nudges')->default(true)->after('share_achievements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'share_profile_with_friends',
                'share_daily_tasks',
                'share_achievements',
                'allow_friend_nudges',
            ]);
        });
    }
};
