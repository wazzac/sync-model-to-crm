<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Wazza\SyncModelToCrm\Http\Controllers\BaseController;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;
use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Sync Class CrmController
 * Example: (new CrmController())->setModel($user)->execute();
 *
 * @package Wazza\SyncModelToCrm\Http\Controllers
 * @version 1.0.0
 * @todo convert the log class to be injected into the controller instead of using the facade
 */

class CrmController extends BaseController
{
    // single execute() actions
    public const EXEC_ACTION_CREATE = 'create';
    public const EXEC_ACTION_UPDATE = 'update';
    public const EXEC_ACTION_DELETE = 'delete';
    public const EXEC_ACTION_RESTORE = 'restore';

    // multiple execute() actions
    public const EXEC_ACTION_CREATE_UPDATE = 'create_update';

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
    private $uniqueFilters = [];

    /**
     * The model to sync
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    private $model;

    /**
     * Create a new CrmController instance and define the log identifier (blank will create a new one)
     *
     * @param string|null $logIdentifier
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(string $logIdentifier = null)
    {
        // parent constructor
        parent::__construct($logIdentifier);

        // anything else to do here?
        // ...
    }

    // --------------------------------------------------------------
    // -- setters
    // --------------------------------------------------------------

    /**
     * Set the environment for the CRM provider
     *
     * @param array|string|null $environment - default to config value
     * @return CrmController
     */
    public function setEnvironment(array|string|null $environment = null)
    {
        $this->environment = $environment ?? config('sync_modeltocrm.api.environment');
        $this->logger->infoLow('Crm Environment: `' . (is_array($this->environment) ? json_encode($this->environment) : $this->environment) . '`');
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
        $this->logger->infoLow('Crm Mapping: `' . json_encode($this->propertyMapping) . '`');
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
        $this->logger->infoLow('Crm Unique Filters: `' . json_encode($this->uniqueFilters) . '`');
        return $this;
    }

    /**
     * Set the model to sync.
     * This Method will also set the default environment, property mapping and unique filters defined in the Model
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return CrmController
     */
    public function setModel($model)
    {
        // -- set the model
        $this->model = $model;
        $this->logger->infoLow('Crm Model set. Class: `' . get_class($this->model) . '`, Table: `' . $this->model->getTable() . '`.');

        // -- set the default environment, property mapping and unique filters defined in the Model
        $this->setEnvironment($model->syncModelCrmEnvironment ?? null);
        $this->setPropertyMapping($model->syncModelCrmPropertyMapping ?? []);
        $this->setUniqueFilters($model->syncModelCrmUniqueSearch ?? []);

        // done
        return $this;
    }

    // --------------------------------------------------------------
    // -- getters
    // --------------------------------------------------------------

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
     * @param bool $processDelete This defines if the CRM record should be deleted/archived if the local record is deleted/archived
     * @param string|array|null $actionEnvironment This defines the environment to sync to (e.g. production, sandbox, etc.)
     * @param string|array|null $actionProvider This defines the CRM provider to sync to (e.g. hubspot, salesforce, etc.)
     * @return void
     */
    public function execute($action = self::EXEC_ACTION_CREATE_UPDATE, $actionEnvironment = null, $actionProvider = null)
    {
        $this->logger->infoMid('Execute the CRM Sync process.');

        // --------------------------------------------------------------
        // --- validation -----------------------------------------------

        // make sure we have a model to sync
        if (empty($this->model)) {
            $this->logger->errorMid('No model provided to sync.');
            throw new Exception('No model provided to sync.');
        }

        // check if we have property mappings to process
        if (empty($this->propertyMapping)) {
            $this->logger->errorMid('No property mappings found to be synced.');
            return;
        }

        // make sure the environment is provided
        if (empty($this->environment)) {
            $this->logger->errorMid('No environment provided to sync to.');
            return;
        }

        // if environment is not an array, convert it to an array
        if (!is_array($this->environment)) {
            $this->environment = [$this->environment];
        }

        // --------------------------------------------------------------
        // --- process the CRM sync -------------------------------------

        // loop the crm environments and initiate each CRM sync individually
        foreach ($this->environment as $environment) {
            // --------------------------------------------------------------
            // check if we can process this environment
            if (!$this->processEnvironment($environment, $actionEnvironment)) {
                $this->logger->infoLow('[' . $environment . '] Skipping environment...');
                continue;
            }
            $this->logger->infoLow('[' . $environment . '] Commence Crm Sync under the `' . $environment . '` environment.');

            // --------------------------------------------------------------
            // loop the property mappings
            foreach ($this->propertyMapping as $provider => $mapping) {
                // --------------------------------------------------------------
                // --- crm level validation and then binding --------------------

                // check if we can process this provider
                if (!$this->processProvider($provider, $actionProvider)) {
                    $this->logger->infoLow('[' . $environment . '][' . $provider . '] Skipping provider...');
                    continue;
                }

                // load the correct CRM provider controller
                $providerController = config('sync_modeltocrm.api.providers.' . $provider . '.controller');
                if (empty($providerController)) {
                    $this->logger->errorMid('No provider controller found for ' . $provider);
                    throw new Exception('No provider controller found for ' . $provider);
                }

                // bind the Provider Translation Controller with the `Crm Controller Interface`
                App::bind(CrmControllerInterface::class, $providerController);
                $this->logger->infoLow('[' . $environment . '][' . $provider . '] Provider Controller ' . $providerController . ' binded to the Crm Class.');

                // --------------------------------------------------------------
                // -- initiate the crm sync request on the binded provider class

                /**
                 * initiate the crm sync request on the binded provider class
                 * @var CrmControllerInterface $crmObject
                 */
                $crmObject = App::make(CrmControllerInterface::class);

                // connect to the crm provider environment and provide the same log identifier
                $crmObject->connect($environment, $this->logger->getLogIdentifier() . '[' . $environment . '][' . $provider . ']');
                $crmObject->logger->infoLow('Connected to Provider environment `' . $environment . '`.');

                // construct the crm property mapping, delete rules and unique filters
                $crmObject->setup($this->model, $mapping);
                $crmObject->logger->infoLow('CRM Property Mapping: ' . json_encode($mapping) . '.');

                // look in the object mapping table to see if we can find the crm object primary key
                $keyLookup = SmtcExternalKeyLookup::where('object_id', $this->model->id)
                    ->where('object_type', $this->model->getTable())
                    ->where('ext_provider', $provider)
                    ->where('ext_environment', $environment)
                    ->first();

                // get the search filters
                $crmFilters = $this->uniqueFilters[$provider] ?? [];

                // load the crm data (if exists)
                $crmObject->load($keyLookup->ext_object_id ?? null, $crmFilters);

                // define what action is requested (create, update, delete, restore, create_update)
                switch ($action) {
                    case self::EXEC_ACTION_CREATE_UPDATE:
                    case self::EXEC_ACTION_CREATE:
                        // make sure that we only create if no object could be loaded
                        // important: model property `syncModelCrmPropertyMapping` should be defined
                        if ($crmObject->getCrmObjectItem() === null) {
                            // process insert
                            $crmObject->logger->infoLow('CRM Object not found. Creating...');
                            $crmObject->create();
                        }
                        break;

                    case self::EXEC_ACTION_CREATE_UPDATE:
                    case self::EXEC_ACTION_UPDATE:
                        // make sure that we only create if no object could be loaded
                        // important: model property `syncModelCrmPropertyMapping` should be defined
                        if ($crmObject->getCrmObjectItem() !== null) {
                            // process update
                            $crmObject->logger->infoLow('CRM Object found. Updating...');
                            $crmObject->update();
                        }
                        break;

                    case self::EXEC_ACTION_DELETE:
                        // make sure that we only create if no object could be loaded
                        // important: model property `syncModelCrmDeleteRules` should be defined
                        if ($crmObject->getCrmObjectItem() !== null) {
                            // process delete
                            $crmObject->logger->infoLow('CRM Object found. Deleting...');
                            $crmObject->delete();
                        }
                        break;

                    case self::EXEC_ACTION_RESTORE:
                        // make sure that we only create if no object could be loaded
                        // important: model property `syncModelCrmActiveRules` should be defined
                        if ($crmObject->getCrmObjectItem() !== null) {
                            // process restore
                            $crmObject->logger->infoLow('CRM Object found. Restoring...');
                            $crmObject->update();
                        }
                        break;

                    default:
                        $this->logger->errorMid('Invalid action requested `' . $action . '`.');
                        break;
                }

                // if the $keyLookup result was empty, then create a new record in the object mapping table
                $crmRecord = $crmObject->getCrmObjectItem();
                if (empty($keyLookup) && isset($crmRecord['id']) && !empty($crmRecord['id'])) {
                    // create a new record in the object mapping table
                    $keyLookup = new SmtcExternalKeyLookup();
                    $keyLookup->object_id       = $this->model->id;
                    $keyLookup->object_type     = $this->model->getTable();
                    $keyLookup->ext_provider    = $provider;
                    $keyLookup->ext_environment = $environment;
                    $keyLookup->ext_object_id   = $crmRecord['id'];
                    $keyLookup->save();
                    $crmObject->logger->infoLow('CRM Object Key Lookup created. `' . $keyLookup->object_id . '` to `' . $keyLookup->ext_object_id . '`');
                }

                // done, next provider
                $crmObject->logger->infoLow('CRM Sync completed.');

                // -- cleanup, onto next provider
                $crmObject->disconnect();
                unset($crmObject);
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
            $this->logger->errorMid('No current environment provided.');
            throw new Exception('No current environment provided.');
        }

        // check if we can process this environment
        if (
            !empty($requestedEnvironment) &&
            (
                (is_array($requestedEnvironment) && !in_array($currentEnvironment, $actionEnvironment)) ||
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
            $this->logger->errorMid('No current provider provided.');
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
