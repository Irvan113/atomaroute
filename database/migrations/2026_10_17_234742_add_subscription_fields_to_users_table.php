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
            $table->string('subscription_tier')->default('free')->after('email');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_tier');
            $table->string('subscription_status')->default('active')->after('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_tier', 'subscription_expires_at', 'subscription_status']);
        });
    }
};
