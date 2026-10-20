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
        Schema::create('user_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('critique'); // feature, bug, critique, appreciation, other
            $table->string('subject');
            $table->text('message');
            $table->string('mood')->nullable(); // love, happy, neutral, frustrated
            $table->json('device_info')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, in_progress, resolved
            $table->text('dev_reply')->nullable();
            $table->timestamp('dev_replied_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedbacks');
    }
};
