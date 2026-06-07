<?php

namespace App\Providers;

use App\Services\Billing\BillingGatewayManager;
use App\Services\Billing\PaystackBillingGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaystackBillingGateway::class);

        $this->app->singleton(BillingGatewayManager::class, function ($app) {
            return new BillingGatewayManager(
                [$app->make(PaystackBillingGateway::class)],
                $app->make(\App\Services\Billing\PaymentGatewayConfigurationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
