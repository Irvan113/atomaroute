<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->string('weight_unit', 10)->default('kg')->after('muscle_group'); // kg, lbs, bw
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn('weight_unit');
        });
    }
};
