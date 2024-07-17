<?php

namespace Webigniter\LaravelClient;

use Illuminate\Support\ServiceProvider;

class WebigniterServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Registratie van services en libraries
    }

    public function boot()
    {
        // Publiceren van routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
