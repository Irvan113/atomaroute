<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->default('Sesi Latihan');
            $table->text('notes')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->decimal('total_volume_kg', 10, 2)->default(0);
            $table->integer('xp_earned')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->string('exercise_name');
            $table->string('muscle_group')->default('Full Body');
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')->constrained('workout_exercises')->cascadeOnDelete();
            $table->integer('set_number')->default(1);
            $table->decimal('weight_kg', 8, 2)->default(0);
            $table->integer('reps')->default(0);
            $table->string('set_type')->default('normal'); // 'normal', 'warmup', 'drop', 'failure'
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_pr')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workout_exercises');
        Schema::dropIfExists('workout_sessions');
    }
};
