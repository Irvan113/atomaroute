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
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('reps');
            $table->integer('duration_seconds')->nullable()->after('distance_km');
            $table->string('pace', 20)->nullable()->after('duration_seconds');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->string('cardio_mode', 20)->nullable()->default('standard')->after('weight_unit');
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->decimal('total_distance_km', 8, 2)->nullable()->default(0)->after('total_volume_kg');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('workout_sessions', 'total_distance_km')) {
            Schema::table('workout_sessions', function (Blueprint $table) {
                $table->dropColumn(['total_distance_km']);
            });
        }

        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'duration_seconds', 'pace']);
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn(['cardio_mode']);
        });
    }
};
