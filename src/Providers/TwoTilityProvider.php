<?php

namespace mindtwo\TwoTility\Providers;

use Illuminate\Support\ServiceProvider;
use mindtwo\TwoTility\Testing\Api\Stores\FakeArrayStore;

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
        $this->mergeConfigFrom(__DIR__.'/../../config/two-tility.php', 'two-tility');

        $this->publishes([
            __DIR__.'/../stubs/api_spec.yaml' => base_path('api_spec.yaml'),
            __DIR__.'/../stubs/api_spec.json' => base_path('api_spec.json'),
        ], 'api-fake-config');

        $this->app->singleton(FakeArrayStore::class, function () {
            return new FakeArrayStore;
        });
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $configPath = __DIR__.'/../../config/two-tility.php';

        $this->publishes([
            $configPath => config_path('two-tility.php'),
        ], 'two-tility');
    }
}
