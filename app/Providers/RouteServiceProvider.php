<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')
            ->group(function () {
                $this->mapBackOfficeRoutes();
                $this->mapFrontOfficeRoutes();
            });
    }

    /**
     * Map back office routes - Individual route registrations generated automatically
     */
    protected function mapBackOfficeRoutes(): void
    {
        Route::as('api.back-offices.')
            ->prefix('api/back-offices')
            ->group(function () {
                Route::as('addresses.')->prefix('addresses')->group(base_path('routes/api/back-office/address.php'));
                Route::as('auth.')->prefix('auth')->group(base_path('routes/api/back-office/auth.php'));
                Route::as('businesses.')->prefix('businesses')->group(base_path('routes/api/back-office/business.php'));
                Route::as('cards.')->prefix('cards')->group(base_path('routes/api/back-office/card.php'));
                Route::as('companies.')->prefix('companies')->group(base_path('routes/api/back-office/company.php'));
                Route::as('countries.')->prefix('countries')->group(base_path('routes/api/back-office/country.php'));
                Route::as('currencies.')->prefix('currencies')->group(base_path('routes/api/back-office/currency.php'));
                Route::as('invoices.')->prefix('invoices')->group(base_path('routes/api/back-office/invoice.php'));
                Route::as('leads.')->prefix('leads')->group(base_path('routes/api/back-office/lead.php'));
                Route::as('packages.')->prefix('packages')->group(base_path('routes/api/back-office/package.php'));
                Route::as('payments.')->prefix('payments')->group(base_path('routes/api/back-office/payment.php'));
                Route::as('permissions.')->prefix('permissions')->group(base_path('routes/api/back-office/permission.php'));
                Route::as('roles.')->prefix('roles')->group(base_path('routes/api/back-office/role.php'));
                Route::as('services.')->prefix('services')->group(base_path('routes/api/back-office/service.php'));
                Route::as('states.')->prefix('states')->group(base_path('routes/api/back-office/state.php'));
                Route::as('statuses.')->prefix('statuses')->group(base_path('routes/api/back-office/status.php'));
                Route::as('subscriptions.')->prefix('subscriptions')->group(base_path('routes/api/back-office/subscription.php'));
                Route::as('users.')->prefix('users')->group(base_path('routes/api/back-office/user.php'));
            });
    }

    /**
     * Map front office routes - Individual route registrations generated automatically
     */
    protected function mapFrontOfficeRoutes(): void
    {
        Route::as('api.front-offices.')
            ->prefix('api/front-offices')
            ->group(function () {
                Route::as('auth.')->prefix('auth')->group(base_path('routes/api/front-office/auth.php'));
                Route::as('customer-vault.')->prefix('customer-vault')->group(base_path('routes/api/front-office/customer-vault.php'));
                Route::as('guest.')->prefix('guest')->group(base_path('routes/api/front-office/guest.php'));
                Route::as('subscribe.')->prefix('subscribe')->group(base_path('routes/api/front-office/subscribe.php'));
            });
    }
}
