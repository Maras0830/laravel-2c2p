<?php
namespace Maras0830\ToCToP\Providers;

use Illuminate\Support\ServiceProvider;
use Maras0830\ToCToP\ToCToP;

class ToCToPServiceProvider extends ServiceProvider  {
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ToCToP::class, function($app) {
            $config = $app['config']['to_c_to_p'];

            return new ToCToP($config['MerchantID'], $config['SecretKey']);
        });

        $this->app->alias(ToCToP::class, 'ToCToP');
    }

    /**
     *  Boot
     */
    public function boot()
    {
        $this->addConfig();
    }

    /**
     *  Config publishing
     */
    private function addConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/to_c_to_p.php' => config_path('to_c_to_p.php')
        ]);
    }
}
