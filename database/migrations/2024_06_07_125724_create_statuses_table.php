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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->string('model', 100);
            $table->string('name', 100);
            $table->string('color', 100)->nullable();
            $table->string('icon', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['model', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
