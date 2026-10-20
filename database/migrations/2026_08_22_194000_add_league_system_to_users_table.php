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
            $table->string('league')->default('bronze_3')->after('longest_streak');
            $table->string('prev_league')->nullable()->after('league');
            $table->integer('prev_week_xp')->default(0)->after('prev_league');
            $table->timestamp('league_promoted_at')->nullable()->after('prev_week_xp');
            $table->boolean('league_season_claimed')->default(true)->after('league_promoted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'league',
                'prev_league',
                'prev_week_xp',
                'league_promoted_at',
                'league_season_claimed'
            ]);
        });
    }
};
