<?php

namespace AmiPraha\AiItemFinder;

use Illuminate\Support\ServiceProvider;

class AiItemFinderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-item-finder.php',
            'ai-item-finder'
        );

        $this->app->bind('ai-item-finder', function ($app) {
            return new AiItemFinder();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai-item-finder.php' => config_path('ai-item-finder.php'),
            ], 'ai-item-finder-config');
        }
    }
}
