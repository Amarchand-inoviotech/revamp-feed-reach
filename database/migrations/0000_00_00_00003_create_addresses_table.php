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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->foreignId('state_id')->constrained('states');
            $table->string('name', 100)->nullable();
            $table->string('phone',100)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2',255)->nullable();
            $table->string('city',100)->nullable();
            $table->string('zip',100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
