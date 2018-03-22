<?php

namespace Sirgrimorum\PaymentPass;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class PaymentPassServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/paymentpass.php' => config_path('sirgrimorum/paymentpass.php'),
                ], 'config');
        $this->loadViewsFrom(__DIR__ . '/Views', 'paymentpass');
        $this->publishes([
            __DIR__ . '/Views' => resource_path('views/vendor/sirgrimorum/paymentpass'),
                ], 'views');
        $this->loadTranslationsFrom(__DIR__ . 'Lang', 'paymentpass');
        $this->publishes([
            __DIR__ . '/Lang' => resource_path('lang/vendor/paymentpass'),
                ], 'lang');
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');

    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
                __DIR__ . '/Config/paymentpass.php', 'sirgrimorum.paymentpass'
        );
        $loader = AliasLoader::getInstance();
            $loader->alias(
                    'PaymentPass', PaymentPass::class
            );
        $this->app->singleton(PaymentPass::class, function($app) {
            return new PaymentPass($app);
        });
    }
}