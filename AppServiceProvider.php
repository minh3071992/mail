<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\MailMagazineManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('mail.magazine.manager', function ($app) {
            return new MailMagazineManager($app);
        });
        $this->app->bind('send.mail.magazine', function ($app, $config) {
            return $mailer = app('mail.magazine.manager')->mailer($config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
