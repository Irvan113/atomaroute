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
        // 1. Users: Add locale column if not exists
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 10)->default('id')->after('timezone');
            });
        }

        // 2. Journals: Add mood index and composite user_id + created_at
        if (Schema::hasTable('journals')) {
            Schema::table('journals', function (Blueprint $table) {
                if (Schema::hasColumn('journals', 'mood')) {
                    $table->index('mood');
                }
                if (Schema::hasColumns('journals', ['user_id', 'created_at'])) {
                    $table->index(['user_id', 'created_at']);
                }
            });
        }

        // 3. Finance Transactions: Add composite user_id + type
        if (Schema::hasTable('finance_transactions')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                if (Schema::hasColumns('finance_transactions', ['user_id', 'type'])) {
                    $table->index(['user_id', 'type']);
                }
            });
        }

        // 4. Tasks: Add composite user_id + created_at
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (Schema::hasColumns('tasks', ['user_id', 'created_at'])) {
                    $table->index(['user_id', 'created_at']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }

        if (Schema::hasTable('journals')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropIndex(['mood']);
                $table->dropIndex(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('finance_transactions')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'type']);
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'created_at']);
            });
        }
    }
};
