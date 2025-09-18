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
        Schema::create('gateway_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->foreignId(column: 'payment_method_id')->constrained('payment_methods');
            $table->foreignId(column: 'package_id')->constrained('packages');
            $table->string("gateway_id", 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payment_method_id', 'package_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_packages');
    }
};
