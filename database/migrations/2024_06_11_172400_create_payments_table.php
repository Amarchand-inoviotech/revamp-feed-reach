<?php

use App\Enum\MerchantStatusEnum;
use App\Enum\PaymentStatusEnum;
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
        Schema::create('payments', function (Blueprint $table) {
              $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->string('gateway_id')->nullable()->comment('service provider transaction id ie. nmi,paypal,stripe, etc.');
            $table->string('note')->nullable();
            $table->decimal('amount', 10, 2)->default(0)->comment('actual currency')->index();
            $table->decimal('usd_amount', 10, 2)->default(0)->comment('usd converted currency');
            $table->enum('type', ['credit', 'debit', 'cancel'])->nullable();
            $table->json('response')->nullable()->comment('service_response');
            $table->ipAddress("client_ip")->nullable();
            $table->string('cc_type', 15)->nullable();
            $table->enum('status', array_column(PaymentStatusEnum::cases(), 'value'))->default(PaymentStatusEnum::INITIATED)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
