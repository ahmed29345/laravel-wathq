<?php

namespace Ahmed29345\Wathq;

use Ahmed29345\Wathq\Console\CheckCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class WathqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wathq.php', 'wathq');

        $this->app->singleton(WathqClient::class, fn (Application $app) => new WathqClient(
            $app->make(HttpFactory::class),
            $app->make('cache'),
            (array) $app->make('config')->get('wathq', []),
        ));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'wathq');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wathq.php' => $this->app->configPath('wathq.php'),
            ], 'wathq-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/wathq'),
            ], 'wathq-lang');

            $this->commands([CheckCommand::class]);
        }
    }
}
