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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('target_value')->default(100);
            $table->integer('current_value')->default(0);
            $table->string('unit')->nullable()->default('target');
            $table->boolean('is_public')->default(true);
            $table->string('status')->default('active'); // 'active', 'completed'
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->nullOnDelete()->after('skill_target_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('last_secret_protection_at')->nullable()->after('last_task_completed_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_secret_protection_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropColumn('goal_id');
        });

        Schema::dropIfExists('goals');
    }
};
