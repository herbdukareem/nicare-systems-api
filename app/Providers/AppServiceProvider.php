<?php

namespace App\Providers;

use App\Services\Billing\BillingGatewayManager;
use App\Services\Billing\MonnifyBillingGateway;
use App\Services\Billing\PaystackBillingGateway;
use App\Services\Billing\QuicktellerBillingGateway;
use App\Services\Billing\RemitaBillingGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaystackBillingGateway::class);
        $this->app->singleton(MonnifyBillingGateway::class);
        $this->app->singleton(RemitaBillingGateway::class);
        $this->app->singleton(QuicktellerBillingGateway::class);

        $this->app->singleton(BillingGatewayManager::class, function ($app) {
            return new BillingGatewayManager(
                [
                    $app->make(PaystackBillingGateway::class),
                    $app->make(MonnifyBillingGateway::class),
                    $app->make(RemitaBillingGateway::class),
                    $app->make(QuicktellerBillingGateway::class),
                ],
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
