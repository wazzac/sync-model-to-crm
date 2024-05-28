<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Wazza\SyncModelToCrm\Http\Controllers\BaseController;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;
use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Wazza\SyncModelToCrm\Http\Controllers\LogController;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Class CrmController
 *
 * @package Wazza\SyncModelToCrm\Http\Controllers
 * @version 1.0.0
 * @todo convert the log class to be injected into the controller instead of using the facade
 */

class CrmController extends BaseController
{
    /**
     * The CRM provider environment/s to use (e.g. production, sandbox, etc.)
     * Use an array to sync to multiple environments.
     * `null` will take the default from the config file.
     *
     * @var string|array|null
     */
    private $environment = null;

    /**
     * Mapping array for local and CRM properties
     * Structure:
     *   $propertyMapping = [
     *     'provider' => [
     *         '{{local_property}}' => '{{crm_property}}',
     *         '{{local_property}}' => '{{crm_property}}',
     *      ],
     *      'hubspot' => [
     *         'name' => 'firstname',
     *         'email' => 'email',
     *       ],
     *   ];
     *
     * @var array
     */
    private $propertyMapping = [];

    /**
     * Unique filters for the CRM to locate the record if there is no internal mapping available.
     * For example, a collection of filters that would load a specific object (like a user).
     * e.g. `email` for a contact or `website` for a entity/company
     *
     * $uniqueFilters =  [
     *     'provider' => [
     *         '{{local_property}}' => '{{crm_property}}',
     *      ],
     *      'hubspot' => [
     *         'email' => 'email',
     *       ],
     *   ];
     *
     * @var array
     */
    public $uniqueFilters = [];

    /**
     * The log identifier to match event sessions
     *
     * @var string
     */
    private $logIdentifier;

    /**
     * The model to sync
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    private $model;

    /**
     * Create a new CrmController instance.
     *
     * @param string|null $logIdentifier
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(string $logIdentifier = null) {
        $this->setLogIdentifier($logIdentifier);
    }

    // --------------------------------------------------------------
    // -- setters
    // --------------------------------------------------------------

    /**
     * Set the log identifier for the CRM provider
     *
     * @param string|null $logIdentifier
     * @return CrmController
     */
    public function setLogIdentifier(?string $logIdentifier = null)
    {
        $this->logIdentifier = $logIdentifier ?? hash('crc32', microtime(true) . rand(10000, 99999));
        return $this;
    }

    /**
     * Set the environment for the CRM provider
     *
     * @param array|string|null $environment - default to config value
     * @return CrmController
     */
    public function setEnvironment(array|string|null $environment = null)
    {
        $this->environment = $environment ?? config('sync_modeltocrm.api.environment');
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, 'Crm Environment: `' . $this->environment . '`', $this->logIdentifier);
        return $this;
    }

    /**
     * Set the property mapping for the CRM provider
     *
     * @param array $propertyMapping
     * @return CrmController
     */
    public function setPropertyMapping(array $propertyMapping = [])
    {
        // set the property mappings
        $this->propertyMapping = $propertyMapping;
        // if the array is a single dimension array, then convert it into a multi-dimensional array with '{default provider}' as the key
        if (count($this->propertyMapping) === count($this->propertyMapping, COUNT_RECURSIVE)) {
            $this->propertyMapping = [config('sync_modeltocrm.api.provider') => $this->propertyMapping];
        }
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, 'Crm Mapping: `' . json_encode($this->propertyMapping) . '`', $this->logIdentifier);
        return $this;
    }

    /**
     * Set the unique filters for the CRM provider
     *
     * @param array $uniqueFilters
     * @return CrmController
     */
    public function setUniqueFilters(array $uniqueFilters = [])
    {
        // set the unique filters
        $this->uniqueFilters = $uniqueFilters;
        // if the array is a single dimension array, then convert it into a multi-dimensional array with '{default provider}' as the key
        if (count($this->uniqueFilters) === count($this->uniqueFilters, COUNT_RECURSIVE)) {
            $this->uniqueFilters = [config('sync_modeltocrm.api.provider') => $this->uniqueFilters];
        }
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, 'Crm Unique Filters: `' . json_encode($this->uniqueFilters) . '`', $this->logIdentifier);
        return $this;
    }

    /**
     * Set the model to sync
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return CrmController
     */
    public function setModel($model)
    {
        // -- set the model
        $this->model = $model;
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, 'Crm Model set. Class: `' . get_class($this->model) . '`, Table: `' . $this->model->getTable() . '`.', $this->logIdentifier);

        // -- set the default environment, property mapping and unique filters defined in the Model
        $this->setEnvironment($model->smtcEnvironment ?? null);
        $this->setPropertyMapping($model->smtcPropertyMapping ?? []);
        $this->setUniqueFilters($model->smtcUniqueFilters ?? []);

        // done
        return $this;
    }

    // --------------------------------------------------------------
    // -- getters
    // --------------------------------------------------------------

    /**
     * Get the current log identifier for the CRM provider
     *
     * @return string
     */
    public function getLogIdentifier()
    {
        return $this->logIdentifier;
    }

    /**
     * Get the environment for the CRM provider
     *
     * @return array|string|null
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Get the property mapping for the CRM provider
     *
     * @return array
     */
    public function getPropertyMapping()
    {
        return $this->propertyMapping;
    }

    /**
     * Get the unique filters for the CRM provider
     *
     * @return array
     */
    public function getUniqueFilters()
    {
        return $this->uniqueFilters;
    }

    /**
     * Get the model to sync
     *
     * @return \Illuminate\Database\Eloquent\Model $model
     */
    public function getModel()
    {
        return $this->model;
    }

    // --------------------------------------------------------------
    // -- action methods
    // --------------------------------------------------------------

    /**
     * Execute the CRM sync process
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model to sync
     * @param string|array|null $processEnvironment This defines the environment to sync to (e.g. production, sandbox, etc.)
     * @param string|array|null $processProvider This defines the CRM provider to sync to (e.g. hubspot, salesforce, etc.)
     * @return void
     */
    public function execute($processEnvironment = null, $processProvider = null)
    {
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__MID, 'Execute the CRM Sync process.', $this->logIdentifier);

        // make sure we have a model to sync
        if (empty($this->model)) {
            LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No model provided to sync.', $this->logIdentifier);
            throw new Exception('No model provided to sync.');
        }

        // check if we have property mappings to process
        if (empty($this->propertyMapping)) {
            LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No property mappings found to be synced.', $this->logIdentifier);
            return;
        }

        // make sure the environment is provided
        if (empty($this->environment)) {
            LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No environment provided to sync to.', $this->logIdentifier);
            return;
        }

        // if environment is not an array, convert it to an array
        if (!is_array($this->environment)) {
            $this->environment = [$this->environment];
        }

        // loop the crm environments and initiate each CRM sync individually
        foreach ($this->environment as $environment) {
            // check if we can process this environment
            if (!$this->processEnvironment($environment, $processEnvironment)) {
                LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '] Skipping environment...', $this->logIdentifier);
                continue;
            }
            LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '] Commence Crm Sync under the `' . $environment . '` environment.', $this->logIdentifier);

            // loop the property mappings
            foreach ($this->propertyMapping as $provider => $mapping) {
                // check if we can process this provider
                if (!$this->processProvider($provider, $processProvider)) {
                    LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] Skipping provider...', $this->logIdentifier);
                    continue;
                }

                // load the correct CRM provider controller
                $providerController = config('sync_modeltocrm.api.providers.' . $provider . '.controller');
                if (empty($providerController)) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No provider controller found for ' . $provider, $this->logIdentifier);
                    throw new Exception('No provider controller found for ' . $provider);
                }

                // bind the Provider Translation Controller with the `Crm Controller Interface`
                App::bind(CrmControllerInterface::class, $providerController);
                LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] Provider Controller ' . $providerController . ' binded to the Crm Class.', $this->logIdentifier);

                /**
                 * initiate the crm sync request on the binded provider class
                 * @var CrmControllerInterface $crmObject
                 */
                $crmObject = App::make(CrmControllerInterface::class);

                // connect to the crm provider environment and provide the same log identifier
                $crmObject->connect($environment, $this->logIdentifier);
                LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] Connected to Provider environment `' . $environment . '`.', $this->logIdentifier);

                // construct the crm property mapping
                $crmObject->setup($this->model, $mapping);
                LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] CRM Property Mapping: ' . json_encode($mapping) . '.', $this->logIdentifier);

                // look in the object mapping table to see if we can find the crm object primary key
                $keyLookup = SmtcExternalKeyLookup::where('object_id', $this->model->id)
                    ->where('object_type', $this->model->getTable())
                    ->where('ext_provider', $provider)
                    ->where('ext_environment', $environment)
                    ->first();

                // get the search filters
                $filters = $this->uniqueFilters[$provider] ?? [];

                // load the crm data (if exists)
                $crmObject->load($keyLookup->ext_object_id ?? null, $filters);

                // located the user record, lets continue to update it
                if ($crmObject->getCrmObjectItem() !== null) {
                    LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] CRM Object found. Updating...', $this->logIdentifier);
                    $crmObject->update();
                } else {
                    LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] CRM Object not found. Creating...', $this->logIdentifier);
                    $crmObject->create();
                }

                // if the $keyLookup result was empty, then create a new record in the object mapping table
                $crmRecord = $crmObject->getCrmObjectItem();
                if (empty($keyLookup) && isset($crmRecord['id']) && !empty($crmRecord['id'])) {
                    $keyLookup = new SmtcExternalKeyLookup();
                    $keyLookup->object_id = $this->model->id;
                    $keyLookup->object_type = $this->model->getTable();
                    $keyLookup->ext_provider = $provider;
                    $keyLookup->ext_environment = $environment;
                    $keyLookup->ext_object_id = $crmRecord['id'];
                    $keyLookup->save();
                    LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] CRM Object Key Lookup created. `' . $keyLookup->object_id . '` to `'.$keyLookup->ext_object_id.'`', $this->logIdentifier);
                }

                // done, next provider
                LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $environment . '][' . $provider . '] CRM Sync completed.', $this->logIdentifier);
            }
        }
    }

    // --------------------------------------------------------------
    // -- checks
    // --------------------------------------------------------------

    /**
     * Check if we can process the environment
     *
     * @param string|null $currentEnvironment
     * @param null|array|string $requestedEnvironment
     * @return bool
     * @throws Exception
     */
    private function processEnvironment(string $currentEnvironment = null, array|string|null $requestedEnvironment = null)
    {
        // make sure we have a current environment
        if (empty($currentEnvironment)) {
            LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No current environment provided.');
            throw new Exception('No current environment provided.');
        }

        // check if we can process this environment
        if (
            !empty($requestedEnvironment) &&
            (
                (is_array($requestedEnvironment) && !in_array($currentEnvironment, $processEnvironment)) ||
                $requestedEnvironment !== $currentEnvironment
            )
        ) {
            // nah, we can't process this environment
            return false;
        }

        // return true if we can process this environment
        return true;
    }

    /**
     * Check if we can process the provider
     *
     * @param string|null $currentProvider
     * @param null|array|string $requestedProvider
     * @return bool
     * @throws Exception
     */
    private function processProvider(string $currentProvider, array|string|null $requestedProvider = null)
    {
        // make sure we have a current provider
        if (empty($currentProvider)) {
            LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__MID, 'No current provider provided.');
            throw new Exception('No current provider provided.');
        }

        // check if we can process this provider
        if (
            !empty($requestedProvider) &&
            (
                (is_array($requestedProvider) && !in_array($currentProvider, $requestedProvider)) ||
                $requestedProvider !== $currentProvider
            )
        ) {
            // nah, we can't process this provider
            return false;
        }

        // return true if we can process this provider
        return true;
    }
}
