<?php

namespace Wazza\SyncModelToCrm\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Wazza\SyncModelToCrm\Http\Controllers\CrmController;

class SyncModelToCrmServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap services.
     * Allows us to run: // php artisan vendor:publish --tag=sync-modeltocrm-config
     */
    public function boot(): void
    {
        // Publish required config files
        $this->publishes([
            $this->configPath() => config_path('sync_modeltocrm.php'),
        ], 'sync-modeltocrm-config');

        // Publish migration files
        $this->publishes([
            $this->dbMigrationsPath() => database_path('migrations')
        ], 'sync-modeltocrm-migrations');

        // Load the migrations
        $this->loadMigrationsFrom($this->dbMigrationsPath());
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the default config path
        $this->mergeConfigFrom(
            $this->configPath(),
            'sync_modeltocrm'
        );

        // Register the service the package provides.
        //$this->app->singleton(CrmController::class, function () {
        //    return new CrmController();
        //});
    }

    /**
     * Set the config path
     *
     * @return string
     */
    private function configPath(): string
    {
        return __DIR__ . '/../../config/sync_modeltocrm.php';
    }

    /**
     * Set the db migration path
     *
     * @return string
     */
    private function dbMigrationsPath(): string
    {
        return __DIR__ . '/../../database/migrations';
    }
}
