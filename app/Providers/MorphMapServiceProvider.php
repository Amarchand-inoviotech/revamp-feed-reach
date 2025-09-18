<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class MorphMapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register individual model mappers for optimal performance
        Relation::morphMap([
            'Address' => \App\Models\Address::class,
            'Attachment' => \App\Models\Attachment::class,
            'Attribute' => \App\Models\Attribute::class,
            'AttributePackage' => \App\Models\AttributePackage::class,
            'AttributeType' => \App\Models\AttributeType::class,
            'Business' => \App\Models\Business::class,
            'Card' => \App\Models\Card::class,
            'Company' => \App\Models\Company::class,
            'Country' => \App\Models\Country::class,
            'Currency' => \App\Models\Currency::class,
            'GatewayPackage' => \App\Models\GatewayPackage::class,
            'Invoice' => \App\Models\Invoice::class,
            'OtpToken' => \App\Models\OtpToken::class,
            'Package' => \App\Models\Package::class,
            'Payment' => \App\Models\Payment::class,
            'PaymentGateway' => \App\Models\PaymentGateway::class,
            'PaymentHistory' => \App\Models\PaymentHistory::class,
            'PaymentMethod' => \App\Models\PaymentMethod::class,
            'Permission' => \App\Models\Permission::class,
            'Role' => \App\Models\Role::class,
            'Service' => \App\Models\Service::class,
            'State' => \App\Models\State::class,
            'Status' => \App\Models\Status::class,
            'Subscription' => \App\Models\Subscription::class,
            'User' => \App\Models\User::class,
        ]);
    }
}
