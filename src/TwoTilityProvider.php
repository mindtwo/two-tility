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
        $this->publishMigrations();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/two-tility.php', 'two-tility');
        $this->mergeConfigFrom(__DIR__.'/../config/external-api.php', 'external-api');
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $configPath = __DIR__.'/../config/two-tility.php';
        $externalApiConfigPath = __DIR__.'/../config/external-api.php';

        $this->publishes([
            $configPath => config_path('two-tility.php'),
        ], 'two-tility');

        $this->publishes([
            $externalApiConfigPath => config_path('external-api.php'),
        ], 'external-api');
    }

    /**
     * Publish the migration files.
     *
     * @return void
     */
    protected function publishMigrations()
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'external-api-migrations');
    }
}
