<?php

namespace AndreasElia\PostmanGenerator;

use AndreasElia\PostmanGenerator\Commands\ExportPostmanCommand;
use AndreasElia\PostmanGenerator\Commands\ExportSqlmapCommand;
use AndreasElia\PostmanGenerator\Contracts\RouteReader;
use AndreasElia\PostmanGenerator\Processors\RouteProcessor;
use Illuminate\Support\ServiceProvider;

class PostmanGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-exports.php' => config_path('api-exports.php'),
            ], 'postman-config');
        }

        $this->commands([
            ExportPostmanCommand::class,
            ExportSqlmapCommand::class,
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/api-exports.php', 'api-exports'
        );

        // Bind the route reader contract to the default RouteProcessor implementation
        $this->app->bind(RouteReader::class, RouteProcessor::class);
    }
}
