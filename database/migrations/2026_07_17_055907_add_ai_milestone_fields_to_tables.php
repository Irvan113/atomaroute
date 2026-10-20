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
        Schema::table('users', function (Blueprint $table) {
            $table->json('badges')->nullable();
        });

        Schema::table('skill_targets', function (Blueprint $table) {
            $table->text('evaluation_feedback')->nullable();
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->text('prompt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('badges');
        });

        Schema::table('skill_targets', function (Blueprint $table) {
            $table->dropColumn('evaluation_feedback');
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });
    }
};
