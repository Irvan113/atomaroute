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
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('plan_type'); // 'monthly', 'yearly', 'trial', 'lifetime', 'custom'
            $table->string('plan_name');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method')->default('manual_transfer'); // 'manual_transfer', 'admin_grant', 'qris_manual', 'promo'
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // 'active', 'expired', 'revoked'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
