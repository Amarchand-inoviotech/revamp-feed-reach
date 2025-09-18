<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Individual repository bindings generated automatically
        $this->app->bind(\App\Repositories\Contracts\AccountRepositoryContract::class, \App\Repositories\Eloquents\AccountRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AddressRepositoryContract::class, \App\Repositories\Eloquents\AddressRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AttachmentRepositoryContract::class, \App\Repositories\Eloquents\AttachmentRepository::class);
        $this->app->bind(\App\Repositories\Contracts\Auth\UserRepositoryContract::class, \App\Repositories\Eloquents\Auth\UserRepository::class);
        $this->app->bind(\App\Repositories\Contracts\BusinessRepositoryContract::class, \App\Repositories\Eloquents\BusinessRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CardRepositoryContract::class, \App\Repositories\Eloquents\CardRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CompanyRepositoryContract::class, \App\Repositories\Eloquents\CompanyRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CountryRepositoryContract::class, \App\Repositories\Eloquents\CountryRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CurrencyRepositoryContract::class, \App\Repositories\Eloquents\CurrencyRepository::class);

        $this->app->bind(\App\Repositories\Contracts\InvoiceRepositoryContract::class, \App\Repositories\Eloquents\InvoiceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LeadRepositoryContract::class, \App\Repositories\Eloquents\LeadRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PackageRepositoryContract::class, \App\Repositories\Eloquents\PackageRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PaymentRepositoryContract::class, \App\Repositories\Eloquents\PaymentRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PermissionRepositoryContract::class, \App\Repositories\Eloquents\PermissionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\RoleRepositoryContract::class, \App\Repositories\Eloquents\RoleRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ServiceRepositoryContract::class, \App\Repositories\Eloquents\ServiceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\StateRepositoryContract::class, \App\Repositories\Eloquents\StateRepository::class);
        $this->app->bind(\App\Repositories\Contracts\StatusRepositoryContract::class, \App\Repositories\Eloquents\StatusRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SubscriptionRepositoryContract::class, \App\Repositories\Eloquents\SubscriptionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\UserRepositoryContract::class, \App\Repositories\Eloquents\UserRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
