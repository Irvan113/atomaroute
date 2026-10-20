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
        Schema::table('skills', function (Blueprint $table) {
            $table->string('reference_url')->nullable();
        });
        Schema::table('skill_targets', function (Blueprint $table) {
            $table->string('reference_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('reference_url');
        });
        Schema::table('skill_targets', function (Blueprint $table) {
            $table->dropColumn('reference_url');
        });
    }
};
