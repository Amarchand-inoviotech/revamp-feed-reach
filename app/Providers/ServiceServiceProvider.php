<?php

namespace App\Providers;

use App\Services\NmiService;
use App\Services\SubscriptionService;
use App\Models\PaymentMethod;
use App\Models\Account;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Payment Gateway Service Factory
        $this->app->singleton('payment.gateways', function () {
            $gateways = [];

            // Register NMI if account exists
            try {
                $paymentMethod = PaymentMethod::where('name', 'nmi')->first();
                if ($paymentMethod) {
                    $account = Account::where('payment_method_id', $paymentMethod->id)
                        ->where('status', true)
                        ->first();

                    if ($account) {
                        $gateways['nmi'] = new NmiService($account, app()->environment('production'));
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail the application
                logger()->warning('Failed to register NMI gateway', [
                    'error' => $e->getMessage()
                ]);
            }

            return $gateways;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register gateway services with SubscriptionService
        $this->app->afterResolving(SubscriptionService::class, function (SubscriptionService $subscriptionService, $app) {
            $gateways = $app->make('payment.gateways');

            foreach ($gateways as $name => $gateway) {
                $subscriptionService->registerGateway($name, $gateway);
            }
        });
    }
}
