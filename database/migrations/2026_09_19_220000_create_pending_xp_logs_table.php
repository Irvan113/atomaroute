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
        Schema::create('pending_xp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('source', 50)->default('other'); // achievement, task, subtask, pomodoro, workout, flashcard, journal, duel, skill, hidden_mission, other
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('settled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'settled_at']);
            $table->index(['user_id', 'source']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_xp_logs');
    }
};
