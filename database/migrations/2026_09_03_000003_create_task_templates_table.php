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
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = official ATOMA preset
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('Work'); // Work, Study, Health, Finance, Personal
            $table->string('priority')->default('normal'); // low, normal, high
            $table->integer('estimated_minutes')->default(45);
            $table->string('mode')->default('sistematis'); // zen, sistematis
            $table->json('subtasks')->nullable();
            $table->boolean('is_public')->default(false); // public in global community
            $table->integer('clones_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
