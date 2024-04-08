<?php

namespace mindtwo\TwoTility\Tests;

use Illuminate\Support\Facades\Schema;
use mindtwo\TwoTility\Providers\TwoTilityProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        Schema::create('busting_models', function ($table) {
            $table->increments('id');
            $table->string('foo')->nullable();
        });

        Schema::create('cached_attributes', function ($table) {
            $table->increments('id');
        });

        $this->beforeApplicationDestroyed(
            function () {
                Schema::drop('busting_models');
                Schema::drop('cached_attributes');
            },
        );
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            TwoTilityProvider::class,
        ];
    }
}
