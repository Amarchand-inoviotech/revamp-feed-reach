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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->foreignId('company_id')->constrained();
            $table->foreignId('payment_method_id')->constrained();
            $table->string('name')->index();
            $table->string('descriptor')->index();
            $table->string('email')->index();
            $table->double('daily_limit')->default(0);
            $table->double('monthly_limit')->default(0);
            $table->boolean('status')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
