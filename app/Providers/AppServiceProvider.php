<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ChannelPartner;
use App\Observers\ChannelPartnerObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ChannelPartner::observe(ChannelPartnerObserver::class);
    }
}
