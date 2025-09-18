<?php

use App\Enum\BillingCycleEnum;
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->enum('billing_cycle', array_column(BillingCycleEnum::cases(), 'value'))->default('yearly');
            $table->foreignId(column: 'user_id')->constrained('users');
            $table->foreignId(column: 'gateway_package_id')->constrained('gateway_packages');
            $table->string('getway_subscription_id', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->enum('status', ['active', 'canceled', 'paused', 'expired'])->default('active');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
