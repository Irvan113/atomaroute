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
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('estimated_minutes')->default(30)->after('is_completed');
            $table->integer('actual_minutes')->default(0)->after('estimated_minutes');
            $table->integer('pomodoro_sessions')->default(0)->after('actual_minutes');
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete()->after('skill_target_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->float('xp_multiplier')->default(1.0)->after('streak_coupons');
            $table->integer('daily_clear_streak')->default(0)->after('xp_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['shared_with_user_id']);
            $table->dropColumn(['estimated_minutes', 'actual_minutes', 'pomodoro_sessions', 'shared_with_user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['xp_multiplier', 'daily_clear_streak']);
        });
    }
};
