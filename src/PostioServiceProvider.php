<?php

namespace Postio\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Postio\Cache\FailSafeCache;
use Postio\Client;
use Postio\Postio;

class PostioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postio.php', 'postio');

        // Built on each resolve from Laravel's HTTP client, so Http::fake() and
        // Http::preventStrayRequests() in an application's tests apply to it.
        $this->app->bind(Postio::class, function (Application $app): Postio {
            $config = $app['config']['postio'];
            $client = new Client((string) $config['key'], Http::buildClient(), (float) $config['timeout'], (int) $config['retries']);
            $cache = new FailSafeCache(Cache::store($config['cache_store'] ?: null));

            return (new Postio($client, $cache, (int) $config['cache_ttl']))->setMaxLines((int) $config['max_lines']);
        });

        $this->app->bind(AddressLookup::class, fn (Application $app) => new AddressLookup(
            fn () => $app->make(Postio::class),
            (bool) $app['config']['postio.key']
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/postio.php' => config_path('postio.php')], 'postio-config');
        }
    }
}
