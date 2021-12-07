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
        \Queue::looping(static function () {
            while (\DB::transactionLevel() > 0) {
                \DB::rollBack();
            }
        });

        // global view variables
        View::share('baseUrl', env('APP_URL'));
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
