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
            $table->index(['user_id', 'date', 'is_completed'], 'tasks_user_date_completed_idx');
            $table->index(['user_id', 'mode'], 'tasks_user_mode_idx');
            $table->index(['user_id', 'is_completed'], 'tasks_user_completed_idx');
        });

        Schema::table('flashcards', function (Blueprint $table) {
            $table->index(['user_id', 'next_review_at'], 'flashcards_user_next_review_idx');
            $table->index(['user_id', 'deck'], 'flashcards_user_deck_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_date_completed_idx');
            $table->dropIndex('tasks_user_mode_idx');
            $table->dropIndex('tasks_user_completed_idx');
        });

        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex('flashcards_user_next_review_idx');
            $table->dropIndex('flashcards_user_deck_idx');
        });
    }
};
