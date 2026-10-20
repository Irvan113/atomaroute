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
        Schema::create('skill_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->integer('day_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->string('status')->default('pending'); // pending, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_targets');
    }
};
