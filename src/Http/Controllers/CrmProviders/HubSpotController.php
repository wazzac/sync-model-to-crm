<?php

namespace Wazza\SyncModelToCrm\Http\Controllers\CrmProviders;

use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Exception;

class HubSpotController implements CrmControllerInterface
{
    /**
     * The HubSpot API client
     * @var \HubSpot\Client
     */
    public $client;

    /**
     * The HubSpot object
     * @var array
     */
    public $object;

    /**
     * Connect to the HubSpot API
     *
     * @return \HubSpot\Discovery\Discovery
     * @throws Exception
     */
    public function connect()
    {
        // get the environment configuration details
        $envConf = self::getEnvironmentConf();

        // load and return the crm connection
        $this->client = \HubSpot\Factory::createWithAccessToken($envConf['access_token']);

        return $this;
    }
}
