<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Wazza\SyncModelToCrm\Http\Controllers\BaseController;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;

class CrmController extends BaseController
{
    /**
     * The config defined crm provider to use. hubspot / pipedrive / salesforce / zohocrm / etc.
     * @var string
     */
    protected $crmProvider;

    /**
     * The config defined crm environment to use. production / sandbox
     * @var string
     */
    protected $crmEnvironment;

    /**
     * The chosen crm provider controller
     * @var \Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface
     */
    public $crmProviderController;

    /**
     * Create a new CrmController instance.
     *
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct()
    {
        LogController::log('notice', 2, 'New CrmController init (construct).');

        // get the crm provder from the config
        $this->crmProvider = config('sync_modeltocrm.api.provider');
        LogController::log('notice', 3, 'Crm Provider: `' . $this->crmProvider . '`');

        // get the crm environment from the config
        $this->crmEnvironment = config('sync_modeltocrm.api.environment');
        LogController::log('notice', 3, 'Crm Environment: `' . $this->crmEnvironment . '`');

        // get the crm provider controller to bind
        $providerController = config('sync_modeltocrm.api.' . $this->crmProvider . '.controller');
        LogController::log('notice', 3, 'Crm Provider Controller: `' . $providerController . '`');

        // bind the Provider Translation Controller with the `Cloud Translate Interface`
        App::bind(\Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface::class, $providerController);
        LogController::log('notice', 3, 'Successfully bound the Crm Provider Controller: `' . $providerController . '` with the `Crm Controller Interface`.');

        // get the crm provider controller
        $this->crmProviderController = App::make(\Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface::class)->connect();
    }

    public function dumpInterface() {
        var_dump($this->crmProviderController);
    }
}
