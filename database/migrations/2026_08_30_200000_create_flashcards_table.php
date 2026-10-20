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
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('front'); // Question / Prompt / Concept
            $table->text('back'); // Answer / Explanation / Formula
            $table->string('deck')->default('Umum');
            $table->integer('repetitions')->default(0);
            $table->integer('interval_days')->default(1);
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('next_review_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
