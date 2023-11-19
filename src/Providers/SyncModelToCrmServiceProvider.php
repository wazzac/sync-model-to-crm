<?php

namespace Wazza\SyncModelToCrm\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class SyncModelToCrmServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * Allows us to run: // php artisan vendor:publish -tag=dom-translate-config
     */
    public function boot(): void
    {
        // Publish required config files
        $this->publishes(
            [$this->configPath() => config_path('sync_modeltocrm.php')],
            'sync-modeltocrm-config'
        );
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
}
