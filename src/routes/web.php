<?php

use Illuminate\Support\Facades\Route;
use Webigniter\LaravelClient\Controllers\WebigniterController;

Route::any('{any}', [WebigniterController::class, 'index'])->where('any', '.*')->fallback();
