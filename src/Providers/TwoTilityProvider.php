<?php

namespace mindtwo\TwoTility\Providers;

class TwoTilityProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('TwoTility', function () {
            return new TwoTility();
        });
    }
}
