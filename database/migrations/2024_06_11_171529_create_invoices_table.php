<?php

use App\Enum\InvoiceStatusEnum;
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
        Schema::create('invoices', function (Blueprint $table) {
              $table->id();
            $table->uuid()->unique()->index();
            $table->morphs('author');
            $table->morphs('model');
            $table->unsignedTinyInteger('currency_id');
            $table->foreignId('account_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->string('note')->nullable();
            $table->decimal('amount',10,2)->unsigned()->default(0);
            $table->decimal('usd_amount',10,2)->unsigned()->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status',  array_column(InvoiceStatusEnum::cases(), 'value'))->default(value: InvoiceStatusEnum::UNPAID->value);
            $table->unsignedTinyInteger('payment_attempts')->default(0);
            $table->timestamp('last_payment_attempt_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
