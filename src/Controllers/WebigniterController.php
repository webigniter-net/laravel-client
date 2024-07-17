<?php

namespace Webigniter\LaravelClient\Controllers;

use App\Http\Controllers\Controller;
use Webigniter\LaravelClient\Libraries\WebigniterClient;

class WebigniterController extends Controller
{
    public function index()
    {
        $webigniter = new WebigniterClient(apiKey: env('WEBIGNITER_KEY'), bypassMaintenance: env('WEBIGNITER_BYPASS_MAINTENANCE', false), subsite: env('WEBIGNITER_SUBSITE', null));

        return $webigniter->getLayoutFile();
    }
}
