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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('deadline')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high
            $table->string('category')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('mode')->default('zen'); // zen, sistematis
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
