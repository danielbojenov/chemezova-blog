<?php

namespace App\Providers;

use App\Support\Site\SiteNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, fn (): ImageManager => new ImageManager(GdDriver::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * The site header is an anonymous component inside the shared layout, so it has
         * no controller to receive the configured navigation links. A composer keeps the
         * lookup out of the templates and only runs it when the header actually renders.
         */
        ViewFacade::composer('components.site.header', fn (View $view): View => $view->with(
            'navigationLinks',
            SiteNavigation::headerLinks(),
        ));
    }
}
