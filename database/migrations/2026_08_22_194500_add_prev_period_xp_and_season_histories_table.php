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
            $table->integer('prev_month_xp')->default(0)->after('prev_week_xp');
            $table->integer('prev_year_xp')->default(0)->after('prev_month_xp');
        });

        Schema::create('leaderboard_season_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type'); // 'weekly', 'monthly', 'yearly'
            $table->string('period_key'); // e.g. '2026-W33', '2026-07', '2025'
            $table->integer('xp')->default(0);
            $table->integer('rank')->default(1);
            $table->string('league')->default('bronze_3');
            $table->timestamps();

            $table->index(['period_type', 'period_key', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_season_histories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prev_month_xp', 'prev_year_xp']);
        });
    }
};
