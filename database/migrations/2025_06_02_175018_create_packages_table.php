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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->string('name', 100);
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('billing_cycle', array_column(BillingCycleEnum::cases(), 'value'))->default('yearly');
            $table->boolean('is_agent')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
