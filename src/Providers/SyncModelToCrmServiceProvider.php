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

        // Register the singleton service the package provides.
        $this->app->singleton(CrmController::class, function () {
            return new CrmController();
        });

        /*
        You can use the above registered singleton service in your application like this:
        $crmController = app(CrmController::class);
        $crmController->someMethod();

        You can also use dependency injection in your controllers or other services.
        For example:
        public function __construct(CrmController $crmController)
        {
            $this->crmController = $crmController;
        }

        This allows you to access the methods of CrmController within your class.
        You can also register other services or bindings as needed.
        $this->app->bind('some.service', function ($app) {
            return new SomeService();
        });
        */
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
