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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->string('name', 100);
            $table->foreignId('llc_address_id')->nullable()->constrained('addresses');
            $table->foreignId('business_address_id')->nullable()->constrained('addresses');
            $table->foreignId('card_id')->nullable()->constrained('cards');
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
