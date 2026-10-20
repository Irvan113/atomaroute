<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friend_nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('nudge_type'); // 'bacok' (duel), 'task', 'fire', 'roast', 'shield'
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'accepted', 'completed', 'declined', 'expired'
            $table->unsignedInteger('target_task_count')->default(3);
            $table->unsignedInteger('sender_task_count')->default(0);
            $table->unsignedInteger('receiver_task_count')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('xp_reward')->default(50);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'receiver_id', 'nudge_type']);
            $table->index(['receiver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friend_nudges');
    }
};
