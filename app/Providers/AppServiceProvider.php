<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // immediately rollback any transaction left open by previous failed queue
        \Queue::looping(function () {
            while (\DB::transactionLevel() > 0) {
                \DB::rollBack();
            }
        });

        // global view variables
        $baseUrl = env('APP_URL');
        $httpDomain = implode('/', array_slice(explode('/', $baseUrl), 0, 3));
        $baseUrlDir = substr($baseUrl, strlen($httpDomain));
        View::share('baseUrl', $baseUrl);
        View::share('httpDomain', $httpDomain);
        View::share('baseUrlDir', $baseUrlDir);
        View::share('reCAPTCHASiteKey', env('reCAPTCHA_SITE_KEY'));
        View::share('GATrackingID', env('GA_TRACKING_ID'));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
