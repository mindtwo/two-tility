<?php

namespace mindtwo\TwoTility;

use Illuminate\Support\ServiceProvider;

class TwoTilityProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/two-tility.php', 'two-tility');
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $configPath = __DIR__.'/../config/two-tility.php';

        $this->publishes([
            $configPath => config_path('two-tility.php'),
        ], 'two-tility');
    }
}
