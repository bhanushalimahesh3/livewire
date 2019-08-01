<?php

namespace Livewire;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;
use Livewire\Commands\LivewireDestroyCommand;
use Livewire\Commands\LivewireMakeCommand;
use Livewire\Connection\HttpConnectionHandler;
use Livewire\LivewireComponentsFinder;
use Livewire\Watchers\DumpWatcher;
use Livewire\Macros\RouteMacros;
use Livewire\Macros\RouterMacros;

class LivewireServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('livewire', LivewireManager::class);

        $this->app->instance(LivewireComponentsFinder::class, new LivewireComponentsFinder(
            new Filesystem, app()->bootstrapPath('cache/livewire-components.php'), app_path('Http/Livewire')
        ));
    }

    public function boot()
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerCommands();
        $this->registerRouterMacros();
        $this->registerBladeDirectives();
        $this->registerWatchers();
    }

    public function registerRoutes()
    {
        RouteFacade::get('/livewire/livewire.js', LivewireJavaScriptAssets::class);

        RouteFacade::post('/livewire/message', HttpConnectionHandler::class);

        // This will be hit periodically by Livewire to make sure the csrf_token doesn't expire.
        RouteFacade::get('/livewire/keep-alive', LivewireKeepAlive::class);
    }

    public function registerViews()
    {
        $this->loadViewsFrom(__DIR__.DIRECTORY_SEPARATOR.'views', 'livewire');
    }

    public function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LivewireMakeCommand::class,
                LivewireDestroyCommand::class,
            ]);

            Artisan::command('livewire:discover', function () {
                app(LivewireComponentsFinder::class)->build();

                $this->info('Livewire auto-discovery manifest rebuilt!');
            });
        }
    }

    public function registerRouterMacros()
    {
        Route::mixin(new RouteMacros);
        Router::mixin(new RouterMacros);
    }

    public function registerBladeDirectives()
    {
        Blade::directive('livewireAssets', function ($expression) {
            return '{!! Livewire::assets('.$expression.') !!}';
        });

        Blade::directive('livewire', [LivewireBladeDirectives::class, 'livewire']);
    }

    public function isLivewireRequest()
    {
        return request()->headers->get('X-Livewire') == true;
    }

    public function registerWatchers()
    {
        $this->app->singleton(DumpWatcher::class, function ($app) {
            return new DumpWatcher();
        });     
    }
}
