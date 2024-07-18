<?php

namespace Webigniter\LaravelClient;

use Illuminate\Support\ServiceProvider;

class WebigniterServiceProvider extends ServiceProvider
{
    public function register()
    {
        // empty
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
