<?php

use App\Enum\LocaleEnum;
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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->unsignedTinyInteger('country_id')->references('id')->on('countries');
            $table->enum('locale',  array_column(LocaleEnum::cases(), 'value'))->default('en');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('domain', 100)->nullable();
            $table->string('email', 100);
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('logo_id')->nullable()->constrained('attachments')->cascadeOnUpdate()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('invoice_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
