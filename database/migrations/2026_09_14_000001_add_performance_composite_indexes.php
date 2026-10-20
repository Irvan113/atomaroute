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
            $table->index(['user_id', 'date'], 'tasks_user_id_date_index');
            $table->index(['user_id', 'is_completed'], 'tasks_user_id_is_completed_index');
        });

        Schema::table('sub_tasks', function (Blueprint $table) {
            $table->index(['task_id', 'is_completed'], 'sub_tasks_task_id_is_completed_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['monthly_xp', 'xp'], 'users_monthly_xp_xp_index');
            $table->index(['weekly_xp', 'xp'], 'users_weekly_xp_xp_index');
            $table->index('xp', 'users_xp_index');
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'finance_transactions_user_id_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_id_date_index');
            $table->dropIndex('tasks_user_id_is_completed_index');
        });

        Schema::table('sub_tasks', function (Blueprint $table) {
            $table->dropIndex('sub_tasks_task_id_is_completed_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_monthly_xp_xp_index');
            $table->dropIndex('users_weekly_xp_xp_index');
            $table->dropIndex('users_xp_index');
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('finance_transactions_user_id_date_index');
        });
    }
};
