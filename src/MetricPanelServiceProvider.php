<?php

declare(strict_types=1);
namespace MetricPanel\Laravel;

use Illuminate\Support\ServiceProvider;
use MetricPanel\Client;

final class MetricPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/metricpanel.php', 'metricpanel');
        $this->app->singleton(Client::class, fn ($app) => new Client(
            (string) $app['config']->get('metricpanel.token'),
            (string) $app['config']->get('metricpanel.api_url'),
        ));
        $this->app->singleton(MetricPanel::class, fn ($app) => new MetricPanel(
            $app->make(\Illuminate\Contracts\Bus\Dispatcher::class),
            (string) $app['config']->get('metricpanel.connection'),
            (string) $app['config']->get('metricpanel.queue'),
            (string) $app['config']->get('queue.connections.'.$app['config']->get('metricpanel.connection').'.driver'),
        ));
    }
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/metricpanel.php' => config_path('metricpanel.php')], 'metricpanel-config');
    }
}
