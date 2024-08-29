<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Wazza\SyncModelToCrm\Http\Controllers\BaseController;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;
use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
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

    // set attempt actions
    private $attemptCreate = false;
    private $attemptUpdate = false;
    private $attemptDelete = false;
    private $attemptRestore = false;

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
     * This is the associated crm object and local model structure.
     * i.e. [
     *      'assocMethod'   => 'entity',
     *      'assocModel'    => \App\Models\Entity::class,
     *      'crmProvider' =>
     *      [
     *          'hubspot' => [
     *              'crmObject'     => 'company',
     *              'crmAssocSpec'  => [
     *                  [
     *                      'association_category' => HubSpotController::ASSOCIATION_CATEGORY__HUBSPOT_DEFINED,
     *                      'association_type_id' => HubSpotController::ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY_PRIMARY,
     *                  ],
     *                  [
     *                      'association_category' => HubSpotController::ASSOCIATION_CATEGORY__HUBSPOT_DEFINED,
     *                      'association_type_id' => HubSpotController::ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY,
     *                  ],
     *              ],
     *          ],
     *      ],
     *  ], [...]
     *
     * This will only work if the Model has the `syncModelCrmAssociateRules` property set.
     *
     * @var array
     */
    private $associateRules = [];

    /**
     * Create a new CrmController instance and define the log identifier (blank will create a new one)
     *
     * @param string|null $logIdentifier
     * @param array|string|null $actions - null/default to patch (insert or update)
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(string $logIdentifier = null, array|string|null $actions = null)
    {
        // parent constructor
        parent::__construct($logIdentifier);

        // set all actions to false
        $this->setAttemptAll(false);

        // make sure actions is an array
        if (empty($actions)) {
            $actions = [self::EXEC_ACTION_CREATE, self::EXEC_ACTION_UPDATE];
        }

        // set defined actions as per the provided array
        if (!is_array($actions)) {
            $actions = [$actions];
        }

        // loop the actions and set the attempt actions
        foreach ($actions as $action) {
            switch ($action) {
                case self::EXEC_ACTION_CREATE:
                    $this->setAttemptCreate(true);
                    break;
                case self::EXEC_ACTION_UPDATE:
                    $this->setAttemptUpdate(true);
                    break;
                case self::EXEC_ACTION_DELETE:
                    $this->setAttemptDelete(true);
                    break;
                case self::EXEC_ACTION_RESTORE:
                    $this->setAttemptRestore(true);
                    break;
                default:
                    $this->logger->errorMid('Invalid action requested `' . $action . '`.');
                    break;
            }
        }

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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return $this
     */
    public function setModel(Model $model = null)
    {
        // -- set the model
        $this->model = $model;

        // -- log the model set (and set the append to model type)
        $this->logger->setLogIdentifier('[' . get_class($this->model) . ']', true);
        $this->logger->infoLow('Crm Model set. Class: `' . get_class($this->model) . '`, Table: `' . $this->model->getTable() . '`.');

        // -- set the default environment, property mapping and unique filters defined in the Model
        $this->setEnvironment($model->syncModelCrmEnvironment ?? null);
        $this->setPropertyMapping($model->syncModelCrmPropertyMapping ?? []);
        $this->setUniqueFilters($model->syncModelCrmUniqueSearch ?? []);
        $this->setAssociateRules($model->syncModelCrmAssociateRules ?? []);

        // done
        return $this;
    }

    /**
     * Set the Associated Models
     *
     * @var array $modelCrmAssociateRules
     * @return $this
     */
    public function setAssociateRules(array $modelCrmAssociateRules = [])
    {
        $this->associateRules = $modelCrmAssociateRules ?? [];
        $this->logger->infoLow('Crm Associated Model Rules set as: `' . json_encode($this->associateRules) . '`');
        return $this;
    }

    /**
     * Set the attempt actions for `create`
     *
     * @param bool $attemptCreate
     * @return $this
     */
    public function setAttemptCreate(bool $attemptCreate = true)
    {
        $this->attemptCreate = $attemptCreate;
        $this->logger->infoLow('Set Attempt Create: ' . ($attemptCreate ? 'true' : 'false'));
        return $this;
    }

    /**
     * Set the attempt actions for `update`
     *
     * @param bool $attemptUpdate
     * @return $this
     */
    public function setAttemptUpdate(bool $attemptUpdate = true)
    {
        $this->attemptUpdate = $attemptUpdate;
        $this->logger->infoLow('Set Attempt Update: ' . ($attemptUpdate ? 'true' : 'false'));
        return $this;
    }

    /**
     * Set the attempt actions for `delete`
     *
     * @param bool $attemptDelete
     * @return $this
     */
    public function setAttemptDelete(bool $attemptDelete = true)
    {
        $this->attemptDelete = $attemptDelete;
        $this->logger->infoLow('Set Attempt Delete: ' . ($attemptDelete ? 'true' : 'false'));
        return $this;
    }

    /**
     * Set the attempt actions for `restore`
     *
     * @param bool $attemptRestore
     * @return $this
     */
    public function setAttemptRestore(bool $attemptRestore = true)
    {
        $this->attemptRestore = $attemptRestore;
        $this->logger->infoLow('Set Attempt Restore: ' . ($attemptRestore ? 'true' : 'false'));
        return $this;
    }

    /**
     * Set the attempt actions for `patch`
     *
     * @param bool $attemptPatch
     * @return $this
     */
    public function setAttemptPatch(bool $attemptPatch = true)
    {
        $this->setAttemptCreate($attemptPatch);
        $this->setAttemptUpdate($attemptPatch);
        return $this;
    }

    /**
     * Set the attempt actions for all actions
     *
     * @param bool $attemptAll
     * @return $this
     */
    public function setAttemptAll(bool $attemptAll = true)
    {
        $this->setAttemptCreate($attemptAll);
        $this->setAttemptUpdate($attemptAll);
        $this->setAttemptDelete($attemptAll);
        $this->setAttemptRestore($attemptAll);
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

    /**
     * Get the Associated Models
     *
     * @return array
     */
    public function getAssociateRules()
    {
        return $this->associateRules;
    }

    /**
     * Get the attempt actions for `create`
     *
     * @return bool
     */
    public function shouldCreate()
    {
        return $this->attemptCreate;
    }

    /**
     * Get the attempt actions for `update`
     *
     * @return bool
     */
    public function shouldUpdate()
    {
        return $this->attemptUpdate;
    }

    /**
     * Get the attempt actions for `delete`
     *
     * @return bool
     */
    public function shouldDelete()
    {
        return $this->attemptDelete;
    }

    /**
     * Get the attempt actions for `restore`
     *
     * @return bool
     */
    public function shouldRestore()
    {
        return $this->attemptRestore;
    }

    /**
     * Get the attempt actions for `patch`
     *
     * @return bool
     */
    public function shouldPatch()
    {
        return $this->shouldCreate() && $this->shouldUpdate();
    }

    /**
     * Get the attempt actions for all actions
     *
     * @return bool
     */
    public function shouldDoAll()
    {
        return $this->shouldCreate() && $this->shouldUpdate() && $this->shouldDelete() && $this->shouldRestore();
    }

    /**
     * Get the attempt actions for any actions
     *
     * @return bool
     */
    public function shouldDoAny()
    {
        return $this->shouldCreate() || $this->shouldUpdate() || $this->shouldDelete() || $this->shouldRestore();
    }

    /**
     * Get the attempt actions for no actions
     *
     * @return bool
     */
    public function shouldDoNone()
    {
        return !$this->shouldCreate() && !$this->shouldUpdate() && !$this->shouldDelete() && !$this->shouldRestore();
    }

    // --------------------------------------------------------------
    // -- action methods
    // --------------------------------------------------------------

    /**
     * Execute the CRM sync process
     *
     * @param bool $associate Should associations be processed
     * @param string|array|null $actionEnvironment This defines the environment to sync to (e.g. production, sandbox, etc.)
     * @param string|array|null $actionProvider This defines the CRM provider to sync to (e.g. hubspot, salesforce, etc.)
     * @return void
     * @throws Exception
     */
    public function execute(bool $associate = false, string|array|null $actionEnvironment = null, string|array|null $actionProvider = null)
    {
        $this->logger->infoMid('------------------------------------------');
        $this->logger->infoMid('----- Execute a new CRM Sync process -----');

        // --------------------------------------------------------------
        // --- validation -----------------------------------------------

        // make sure we have a model to sync
        if (empty($this->model)) {
            $this->logger->errorMid('No model provided to sync.');
            throw new Exception('No model provided to sync.');
        }

        // make sure we have at least one action to process
        if ($this->shouldDoNone()) {
            $this->logger->errorMid('No action provided to sync.');
            return;
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

                // get the object table mapping
                $configObjectTableMapping = config('sync_modeltocrm.api.providers.' . $provider . '.object_table_mapping');
                if (empty($configObjectTableMapping)) {
                    throw new Exception('No provider object table mapping found for `' . $provider . '`.');
                }

                // set the object type to be used
                // contact, company, deal etc.
                $objectType = $this->model->syncModelCrmRelatedObject ?? array_search($this->model->getTable(), $configObjectTableMapping);

                // load the correct CRM provider controller
                $providerController = config('sync_modeltocrm.api.providers.' . $provider . '.controller');
                if (empty($providerController)) {
                    $this->logger->errorMid('No provider controller found for `' . $provider . '`.');
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
                    ->where('ext_object_type', $objectType)
                    ->first();

                // get the search filters
                $crmFilters = $this->uniqueFilters[$provider] ?? [];

                // set the filter actual values from the model data
                $crmFilterData = [];
                if (!empty($crmFilters)) {
                    foreach ($crmFilters as $localProperty => $crmProperty) {
                        $crmFilterData[$crmProperty] = $this->model->{$localProperty} ?? null;
                    }
                }
                $crmObject->logger->infoLow('Crm filter properties set as: ' . json_encode($crmFilterData));

                // get the external object id
                $extObjectId = $keyLookup->ext_object_id ?? null;

                // load the crm data (if exists)
                $crmObject->load($extObjectId, $crmFilterData);

                // define what action is requested (create, update, delete, restore)
                // -- (1) Patch: create a new record in the CRM -------------------
                if ($this->shouldPatch()) {
                    // make sure that we only create if no object could be loaded
                    // important: model property `syncModelCrmPropertyMapping` should be defined
                    if ($crmObject->getCrmObjectItem() === null) {
                        // process insert
                        $crmObject->logger->infoLow('CRM Object not found. Creating...');
                        $crmObject->create();
                    } else {
                        // process update
                        $crmObject->logger->infoLow('CRM Object found. Updating...');
                        $crmObject->update();
                    }
                }

                // -- (2) Create: create a new record in the CRM ------------------
                if (!$this->shouldPatch() && $this->shouldCreate()) {
                    // make sure that we only create if no object could be loaded
                    // important: model property `syncModelCrmPropertyMapping` should be defined
                    if ($crmObject->getCrmObjectItem() === null) {
                        // process insert
                        $crmObject->logger->infoLow('CRM Object not found. Creating...');
                        $crmObject->create();
                    }
                }

                // -- (3) Update: update an existing record in the CRM -------------
                if (!$this->shouldPatch() && $this->shouldUpdate()) {
                    // make sure that we only create if no object could be loaded
                    // important: model property `syncModelCrmPropertyMapping` should be defined
                    if ($crmObject->getCrmObjectItem() !== null) {
                        // process update
                        $crmObject->logger->infoLow('CRM Object found. Updating...');
                        $crmObject->update();
                    }
                }

                // -- (4) Delete: delete an existing record in the CRM -------------
                if ($this->shouldDelete()) {
                    // make sure that we only create if no object could be loaded
                    // important: model property `syncModelCrmDeleteRules` should be defined
                    if ($crmObject->getCrmObjectItem() !== null) {
                        // process delete
                        $crmObject->logger->infoLow('CRM Object found. Deleting...');
                        $crmObject->delete();
                    }
                }

                // -- (5) Restore: restore an existing record in the CRM -----------
                if ($this->shouldRestore()) {
                    // make sure that we only create if no object could be loaded
                    // important: model property `syncModelCrmActiveRules` should be defined
                    if ($crmObject->getCrmObjectItem() !== null) {
                        // process restore
                        $crmObject->logger->infoLow('CRM Object found. Restoring...');
                        $crmObject->update();
                    }
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
                    $keyLookup->ext_object_type = $objectType;
                    $keyLookup->ext_object_id   = $crmRecord['id'];
                    $keyLookup->save();
                    // log it...
                    $crmObject->logger->infoLow('CRM Object Key Lookup created. `' . $keyLookup->object_id . '` to `' . $keyLookup->ext_object_id . '`');
                }

                // done, next provider
                $crmObject->logger->infoLow('CRM Sync completed.');

                // process the associations
                if ($associate && !empty($this->associateRules)) {
                    // loop the provided crm objects and models
                    foreach ($this->associateRules as $associateRule) {
                        // get the source method name responsible for the associated model
                        $associatedMethod = $associateRule['assocMethod'] ?? null;
                        if (empty($associatedMethod)) {
                            $this->logger->errorMid('No associated model provided, just continue to next modal if applicable.');
                            continue;
                        }

                        // get the source model object
                        $associatedModel = $this->model->{$associatedMethod} ?? null;
                        if (empty($associatedModel)) {
                            $this->logger->errorMid('No associated model could be loaded for `' . $associatedMethod . '`, just continue to next modal if applicable.');
                            continue;
                        }

                        // locate the crmProvider association rules
                        $associationSpecs = $associateRule['provider'][$provider] ?? [];
                        if (empty($associationSpecs)) {
                            $this->logger->errorMid('No association specs found for `' . $provider . '`, just continue to next modal if applicable.');
                            continue;
                        }

                        // get the associated object type
                        $associatedObject = $associatedModel->syncModelCrmRelatedObject ?? null;
                        if (empty($associatedObject)) {
                            $this->logger->errorMid('No associated object type found for `' . $associatedMethod . '`, just continue to next modal if applicable.');
                            continue;
                        }

                        // make sure we received the correct object and model structure
                        if (!array_key_exists($associatedObject, $configObjectTableMapping)) {
                            $associatedObject = $associatedModel->syncModelCrmRelatedObject ?? array_search($associatedModel->getTable(), $configObjectTableMapping);
                        }

                        // look if the associated model has already been saved in the object mapping table
                        $assocKeyLookup = SmtcExternalKeyLookup::where('object_id', $associatedModel->id)
                            ->where('object_type', $associatedModel->getTable())
                            ->where('ext_provider', $provider)
                            ->where('ext_environment', $environment)
                            ->where('ext_object_type', $associatedObject)
                            ->first();

                        // if we have no internal mapping for the associated object, then we need to create it
                        if (empty($assocKeyLookup)) {
                            $crmObject->logger->infoLow('No associated object key lookup found for `' . $associatedObject . '`. Creating...');

                            /**
                             * @var CrmControllerInterface $crmAssocObject
                             */
                            $crmAssocObject = App::make(CrmControllerInterface::class);

                            // connect to the crm provider environment and provide the same log identifier
                            $crmAssocObject->connect($environment, $crmObject->logger->getLogIdentifier() . '[assoc: ' . $associatedObject . ']');
                            $crmAssocObject->logger->infoLow('Connected to Provider environment `' . $environment . '`.');

                            // construct the crm property mapping, delete rules and unique filters
                            $assocMapping = $associatedModel->syncModelCrmPropertyMapping[$provider] ?? [];
                            $crmAssocObject->setup($associatedModel, $assocMapping);
                            $crmAssocObject->logger->infoLow('CRM Property Mapping: ' . json_encode($assocMapping) . '.');

                            // get the search filters
                            $crmAssocFilters = $associatedModel->syncModelCrmUniqueSearch[$provider] ?? [];

                            // set the filter actual values from the model data
                            $crmAssocFilterData = [];
                            if (!empty($crmAssocFilters)) {
                                foreach ($crmAssocFilters as $localProperty => $crmProperty) {
                                    $crmAssocFilterData[$crmProperty] = $associatedModel->{$localProperty} ?? null;
                                }
                            }
                            $crmAssocObject->logger->infoLow('Crm filter properties set as: ' . json_encode($crmAssocFilterData));

                            // load the crm data (if exists)
                            $crmAssocObject->load(null, $crmAssocFilterData);

                            // for this we only create
                            if ($crmAssocObject->getCrmObjectItem() === null) {
                                // process insert
                                $crmAssocObject->logger->infoLow('CRM Object not found. Creating...');
                                $crmAssocObject->create();
                                $crmAssocObject->logger->infoLow('CRM Object created. `' . ($crmAssocObject->getCrmObjectItem()['id'] ?? 'err_no_id') . '`.');
                            } else {
                                // process update
                                $crmAssocObject->logger->infoLow('CRM Object found. Updating...');
                                $crmAssocObject->update();
                                $crmAssocObject->logger->infoLow('CRM Object updated. `' . ($crmAssocObject->getCrmObjectItem()['id'] ?? 'err_no_id') . '`.');
                            }

                            // if the $keyLookup result was empty, then create a new record in the object mapping table
                            $crmAssocRecord = $crmAssocObject->getCrmObjectItem();
                            if (isset($crmAssocRecord['id']) && !empty($crmAssocRecord['id'])) {
                                // create a new record in the object mapping table
                                $assocKeyLookup = new SmtcExternalKeyLookup();
                                $assocKeyLookup->object_id       = $associatedModel->id;
                                $assocKeyLookup->object_type     = $associatedModel->getTable();
                                $assocKeyLookup->ext_provider    = $provider;
                                $assocKeyLookup->ext_environment = $environment;
                                $assocKeyLookup->ext_object_type = $associatedObject;
                                $assocKeyLookup->ext_object_id   = $crmAssocRecord['id'];
                                $assocKeyLookup->save();
                                // log it...
                                $crmAssocObject->logger->infoLow('CRM Object Key Lookup created. `' . $keyLookup->object_id . '` to `' . $keyLookup->ext_object_id . '`');
                            }

                            // -- cleanup, onto next provider
                            $crmAssocObject->disconnect();
                            unset($crmAssocObject);
                        } else {
                            $crmObject->logger->infoLow('CRM Object Key Lookup found. Object ID - `' . $assocKeyLookup->object_id . '` to CRM ID - `' . $assocKeyLookup->ext_object_id . '`');
                        }

                        // as a final check, make sure we have an $assocKeyLookup->ext_object_id set
                        if (empty($assocKeyLookup->ext_object_id ?? null)) {
                            $crmObject->logger->errorMid('No associated object key lookup found for `' . $associatedObject . '`.');
                            continue;
                        }

                        // associate the current object with the associated object
                        $crmObject->associate($associatedObject, $assocKeyLookup->ext_object_id, $associationSpecs);
                    }
                } else {
                    $crmObject->logger->infoLow('No associations to process.');
                }

                // -- cleanup, onto next provider
                $crmObject->disconnect();
                unset($crmObject);
            }
        }

        // done
        $this->logger->infoMid('----- CRM Sync process completed -----');
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
