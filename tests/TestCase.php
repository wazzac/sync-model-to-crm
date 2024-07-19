<?php

namespace Wazza\SyncModelToCrm\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Wazza\SyncModelToCrm\Providers\SyncModelToCrmServiceProvider;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;

abstract class TestCase extends OrchestraTestCase
{
    use DatabaseMigrations, DatabaseTransactions;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->withFactories(__DIR__ . '/../database/factories');
    }

    /**
     * Add the package provider
     *
     * @param $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SyncModelToCrmServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testdb');
        $app['config']->set('database.connections.testdb', [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/testdb.sqlite',
            'prefix' => '',
        ]);
        $app['config']->set('sync_modeltocrm.logging.level', env('SYNC_MODEL_TO_CRM_LOG_LEVEL'));
        $app['config']->set('sync_modeltocrm.logging.indicator', env('SYNC_MODEL_TO_CRM_LOG_INDICATOR'));
        $app['config']->set('sync_modeltocrm.api.provider', env('SYNC_MODEL_TO_CRM_PROVIDER'));
        $app['config']->set('sync_modeltocrm.api.environment', env('SYNC_MODEL_TO_CRM_ENVIRONMENT'));
        $app['config']->set('sync_modeltocrm.hash.salt', env('SYNC_MODEL_TO_CRM_HASH_SALT'));
        $app['config']->set('sync_modeltocrm.hash.algo', env('SYNC_MODEL_TO_CRM_HASH_ALGO'));
        $app['config']->set('sync_modeltocrm.api.providers.hubspot.sandbox.access_token', env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_TOKEN'));
    }

    /**
     * Define aliases for the package
     */
    protected function getPackageAliases($app)
    {
        return [
            'config' => 'Illuminate\Config\Repository'
        ];
    }
}
