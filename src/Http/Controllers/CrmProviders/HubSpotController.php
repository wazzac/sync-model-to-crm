<?php

namespace Wazza\SyncModelToCrm\Http\Controllers\CrmProviders;

use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;
use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Wazza\SyncModelToCrm\Http\Controllers\Logger\LogController;

use Illuminate\Database\Eloquent\Model;

use HubSpot\Client\Crm\Companies\Model\ModelInterface;

use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactSimplePublicObjectInput;
use HubSpot\Client\Crm\Contacts\Model\Filter as ContactFilter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup as ContactFilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest as ContactPublicObjectSearchRequest;
use HubSpot\Client\Crm\Contacts\ApiException as ContactApiException;
use HubSpot\Client\Crm\Contacts\Model\CollectionResponseWithTotalSimplePublicObjectForwardPaging as ContactCollectionResponseWithTotalSimplePublicObjectForwardPaging;

use HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInput as CompanySimplePublicObjectInput;
use HubSpot\Client\Crm\Companies\Model\Filter as CompanyFilter;
use HubSpot\Client\Crm\Companies\Model\FilterGroup as CompanyFilterGroup;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest as CompanyPublicObjectSearchRequest;
use HubSpot\Client\Crm\Companies\ApiException as CompanyApiException;
use HubSpot\Client\Crm\Companies\Model\CollectionResponseWithTotalSimplePublicObjectForwardPaging as CompanyCollectionResponseWithTotalSimplePublicObjectForwardPaging;

use HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec as AssociationSpec;
use HubSpot\Client\Crm\Associations\V4\ApiException as AssociationsApiException;

use Exception;

class HubSpotController implements CrmControllerInterface
{
    private const PROVIDER = 'hubspot';

    // Life Cycle Stages (Property Name `lifecyclestage`; for both contacts and companies)
    // -------------------------
    public const LIFECYCLE_STAGE_PROPERTY_NAME  = 'lifecyclestage';
    // Stages:
    public const LIFECYCLE_STAGE__SUBSCRIBER    = 'subscriber';
    public const LIFECYCLE_STAGE__LEAD          = 'lead';
    public const LIFECYCLE_STAGE__MQL           = 'marketingqualifiedlead';
    public const LIFECYCLE_STAGE__SQL           = 'salesqualifiedlead';
    public const LIFECYCLE_STAGE__OPPORTUNITY   = 'opportunity';
    public const LIFECYCLE_STAGE__CUSTOMER      = 'customer';
    public const LIFECYCLE_STAGE__EVANGELIST    = 'evangelist';
    public const LIFECYCLE_STAGE__OTHER         = 'other';

    // Contact Property - Lead Status (key: hs_lead_status)
    // -------------------------
    public const CRM_CONTACT_LEAD_STATUS__NEW                   = 'NEW';
    public const CRM_CONTACT_LEAD_STATUS__OPEN                  = 'OPEN';
    public const CRM_CONTACT_LEAD_STATUS__IN_PROGRESS           = 'IN_PROGRESS';
    public const CRM_CONTACT_LEAD_STATUS__OPEN_DEAL             = 'OPEN_DEAL';
    public const CRM_CONTACT_LEAD_STATUS__UNQUALIFIED           = 'UNQUALIFIED';
    public const CRM_CONTACT_LEAD_STATUS__ATTEMPTED_TO_CONTACT  = 'ATTEMPTED_TO_CONTACT';
    public const CRM_CONTACT_LEAD_STATUS__CONNECTED             = 'CONNECTED';
    public const CRM_CONTACT_LEAD_STATUS__BAD_TIMING            = 'BAD_TIMING';
    public const CRM_CONTACT_LEAD_STATUS__DELETED               = 'DELETED';

    // --- Associations types & categories
    // @link https://developers.hubspot.com/docs/api/crm/associations
    public const ASSOCIATION_CATEGORY__HUBSPOT_DEFINED = 'HUBSPOT_DEFINED'; // this is the default category with type ids listed in the url above

    // Company to ...
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_CONTACT_PRIMARY    = 2;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_CONTACT            = 280;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_DEAL_PRIMARY       = 6;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_DEAL               = 342;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_TICKET_PRIMARY     = 25;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_TICKET             = 340;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_PARENT_COMPANY     = 14;
    public const ASSOCIATION_TYPE_ID__COMPANY_TO_CHILD_COMPANY      = 13;
    // Contact to ...
    public const ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY_PRIMARY    = 1;
    public const ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY            = 279;
    public const ASSOCIATION_TYPE_ID__CONTACT_TO_CONTACT            = 449;
    public const ASSOCIATION_TYPE_ID__CONTACT_TO_DEAL               = 4;
    public const ASSOCIATION_TYPE_ID__CONTACT_TO_TICKET             = 15;
    // Deal to ...
    public const ASSOCIATION_TYPE_ID__DEAL_TO_DEAL                  = 451;
    public const ASSOCIATION_TYPE_ID__DEAL_TO_CONTACT               = 3;
    public const ASSOCIATION_TYPE_ID__DEAL_TO_COMPANY_PRIMARY       = 5;
    public const ASSOCIATION_TYPE_ID__DEAL_TO_TICKET                = 27;

    /**
     * The logger instance
     *
     * @var LogController
     */
    public $logger;

    /**
     * The HubSpot API client
     *
     * @var \HubSpot\Discovery\Discovery
     */
    public $client;

    /**
     * The HubSpot object properties that will drive the insert and update process
     * Example: [
     *   'hubspot' => [
     *      'name' => 'firstname',
     *      'email' => 'email',
     *   ],
     * ]
     *
     * @var array
     */
    private $properties = [];

    /**
     * The HubSpot object delete rules
     * Example: [
     *   'hard_delete' => [
     *       'hubspot' => false,
     *   ],
     *   'soft_delete' => [
     *       'hubspot' => [
     *           'lifecyclestage' => 'other',
     *           'hs_lead_status' => 'DELETED',
     *       ],
     *   ]
     * ]
     *
     * @var array
     */
    private $deleteRules = [];

    /**
     * The HubSpot object active rules
     * Example: [
     *   'hubspot' => [
     *       'lifecyclestage' => 'customer',
     *       'hs_lead_status' => 'OPEN',
     *   ],
     * ]
     *
     * @var array
     */
    private $activeRules = [];

    /**
     * The HubSpot object association rules
     *
     * @var array
     */
    private $associateRules = [];

    /**
     * The CRM provider environment/s to use
     * (e.g. production, sandbox, etc.)
     *
     * @var string
     */
    private $environment = null;

    /**
     * The HubSpot CRM object type
     * Options: contacts, companies, deals, tickets
     *
     * @var string
     */
    private $crmObjectType;

    /**
     * The HubSpot CRM object
     *
     * @var array
     */
    private $crmObject;

    /**
     * The HubSpot CRM object item
     * @var ModelInterface
     */
    private $crmObjectItem;

    /**
     * Connect to the HubSpot API
     *
     * @param string|null $environment The environment to connect to (e.g. production, sandbox, etc.)
     * @param string|null $logIdentifier The log identifier to match event sessions (Important to match with the event that is calling it)
     * @return self
     * @throws Exception
     */
    public function connect(?string $environment = 'sandbox', ?string $logIdentifier = null)
    {
        // --------------------------------------------------------------
        // set the logger instance
        $this->logger = new LogController($logIdentifier);

        // --------------------------------------------------------------
        // set the environment and log identifier
        $this->environment = $environment;
        $this->logger->infoLow('Init connection...');

        // --------------------------------------------------------------
        // load the crm environment configuration
        $envConf = config('sync_modeltocrm.api.providers.hubspot.' . $this->environment, null);
        if (is_null($envConf)) {
            throw new Exception('HubSpot environment configuration not found.');
        }
        $this->logger->infoLow('Configuration loaded.');

        // --------------------------------------------------------------
        // load and set the crm connection (return the client object)
        $this->client = \HubSpot\Factory::createWithAccessToken($envConf['access_token']);
        $this->logger->infoLow('CRM client (3rd party) connected.');
        return $this;
    }

    /**
     * Disconnect from the HubSpot API
     *
     * @return void
     */
    public function disconnect()
    {
        $this->client = null;
        $this->logger->infoLow('Client disconnected.');
    }

    /**
     * Check if the HubSpot API is connected
     *
     * @return bool
     */
    public function connected()
    {
        return !is_null($this->client);
    }

    /**
     * Setup the property mappings for the HubSpot object
     *
     * @param Model $model
     * @param array $mapping
     * @return self
     */
    public function setup(Model $model, array $mapping)
    {
        // --------------------------------------------------------------
        // check if the model is set
        if (!$this->connected()) {
            throw new Exception('HubSpot API not connected. Connect first using the `connect` method.');
        }

        // --------------------------------------------------------------
        // load the provider crm table mapping
        $objectTableMapping = config('sync_modeltocrm.api.providers.' . self::PROVIDER . '.object_table_mapping');
        if (empty($objectTableMapping)) {
            throw new Exception('No provider object table mapping found for Hubspot.');
        }
        // search for the object table name in the $objectTableMapping and return the key
        $this->crmObjectType = $model->syncModelCrmRelatedObject ?? array_search($model->getTable(), $objectTableMapping);
        $this->logger->infoLow('Object type set to: `' . $this->crmObjectType . '`.');

        // --------------------------------------------------------------
        // set the properties
        $this->properties = [];
        foreach ($mapping as $localProperty => $crmProperty) {
            $this->properties[$crmProperty] = $model->{$localProperty};
        }
        $this->logger->infoLow('Properties set: ' . json_encode($this->properties));

        // --------------------------------------------------------------
        // set the model delete rules
        $this->deleteRules = [];
        $this->deleteRules['hard_delete'] = $model->syncModelCrmDeleteRules['hard_delete'][self::PROVIDER] ?? null;
        $this->deleteRules['soft_delete'] = $model->syncModelCrmDeleteRules['soft_delete'][self::PROVIDER] ?? null;
        $this->logger->infoLow('Delete rules set: ' . json_encode($this->deleteRules));

        // --------------------------------------------------------------
        // set the model active rules
        $this->activeRules = $model->syncModelCrmActiveRules[self::PROVIDER] ?? null;
        $this->logger->infoLow('Active rules set: ' . json_encode($this->activeRules));

        // --------------------------------------------------------------
        // set the model association rules
        $this->associateRules = $model->syncModelCrmAssociateRules[self::PROVIDER] ?? null;
        $this->logger->infoLow('Association rules set: ' . json_encode($this->associateRules));

        // all seems good
        return $this;
    }

    /**
     * Get the HubSpot CRM object (contains multiple crm records)
     * format: [
     *  'total' => 0,
     * 'results' => [],
     * 'paging' => []
     * ]
     *
     * @return ModelInterface|null
     */
    public function getCrmObject()
    {
        return $this->crmObject;
    }

    /**
     * Get the HubSpot CRM object item (the single crm record)
     *
     * @return ModelInterface|null
     */
    public function getCrmObjectItem()
    {
        return $this->crmObjectItem;
    }

    /**
     * Load data from the HubSpot API
     *
     *  @param string|null $crmObjectPrimaryKey Best case, the key lookup object primary key (when already mapped)
     * @param array $searchFilters The filters to apply to find the specific crm object
     * @return self
     */
    public function load(string|null $crmObjectPrimaryKey = null, array $searchFilters = [])
    {
        $this->logger->infoMid('Loading the CRM object with Primary Key: `' . ($crmObjectPrimaryKey ?? 'not set yet') . '` and Filters: `' . json_encode($searchFilters) . '`');

        // flush the object
        $this->crmObjectFlush();
        $this->crmObjectItemFlush();

        // make sure we have a crm object type
        if (empty($this->crmObjectType)) {
            throw new Exception('HubSpot object type not set. First set the object type using the `setup` method.');
        }

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    if (!empty($crmObjectPrimaryKey)) {
                        $this->logger->infoLow('Loading contact by id: ' . $crmObjectPrimaryKey);
                        // load the contact by contact id
                        $this->crmObjectItem = $this->client->crm()->contacts()->basicApi()->getById(
                            $crmObjectPrimaryKey,
                            implode(",", array_keys($this->properties))
                        );
                    } else {
                        $this->logger->infoLow('Searching for contact by filters: ' . json_encode($searchFilters));
                        // create the filter group
                        $filters = [];
                        foreach ($searchFilters as $key => $value) {
                            $filter = new ContactFilter();
                            $filter->setOperator('EQ')->setPropertyName($key)->setValue($value);
                            $filters[] = $filter;
                            unset($filter);
                        }

                        // create the filter group
                        $filterGroup = new ContactFilterGroup();
                        $filterGroup->setFilters($filters);
                        unset($filters);

                        // initiate the search request
                        $searchRequest = new ContactPublicObjectSearchRequest();
                        $searchRequest->setFilterGroups([$filterGroup]);
                        $searchRequest->setProperties(array_keys($this->properties));
                        $searchRequest->setLimit(1);
                        unset($filterGroup);

                        // call search endpoint
                        $this->crmObject = $this->client
                            ->crm()
                            ->contacts()
                            ->searchApi()
                            ->doSearch($searchRequest);

                        // also set the object item (use the first record as the filter should find a unique record)
                        $this->crmObjectItem = $this->crmObject['results'][0] ?? null;
                    }
                } catch (ContactSimplePublicObjectInput $ex) {
                    $this->logger->errorHigh('Error loading contact: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error loading contact: ' . $e->getMessage());
                }
                // done, break out
                break;

            case 'company':
                try {
                    // we have a primary key, load the company by id
                    if (!empty($crmObjectPrimaryKey)) {
                        $this->logger->infoLow('Loading company by id: ' . $crmObjectPrimaryKey);
                        // load the company by company id
                        $this->crmObjectItem = $this->client->crm()->companies()->basicApi()->getById(
                            $crmObjectPrimaryKey,
                            implode(",", array_keys($this->properties))
                        );
                    } else {
                        $this->logger->infoLow('Searching for company by filters: ' . json_encode($searchFilters));
                        // create the filter group
                        $filters = [];
                        foreach ($searchFilters as $key => $value) {
                            $filter = new CompanyFilter();
                            $filter->setOperator('EQ')->setPropertyName($key)->setValue($value);
                            $filters[] = $filter;
                            unset($filter);
                        }

                        // create the filter group
                        $filterGroup = new CompanyFilterGroup();
                        $filterGroup->setFilters($filters);
                        unset($filters);

                        // initiate the search request
                        $searchRequest = new CompanyPublicObjectSearchRequest();
                        $searchRequest->setFilterGroups([$filterGroup]);
                        $searchRequest->setProperties(array_keys($this->properties));
                        $searchRequest->setLimit(1);
                        unset($filterGroup);

                        // call search endpoint
                        $this->crmObject = $this->client
                            ->crm()
                            ->companies()
                            ->searchApi()
                            ->doSearch($searchRequest);

                        // also set the object item (use the first record as the filter should find a unique record)
                        $this->crmObjectItem = $this->crmObject['results'][0] ?? null;
                    }
                } catch (CompanySimplePublicObjectInput $ex) {
                    $this->logger->errorHigh('Error loading company: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error loading company: ' . $e->getMessage());
                }
                // done, break out
                break;

            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        // return the object
        return $this;
    }

    /**
     * Create a new record in the HubSpot API
     *
     * @return $this
     */
    public function create()
    {
        $this->logger->infoMid('Create new record...');

        // flush the object
        $this->crmObjectItemFlush();

        // make sure we have a crm object type
        if (empty($this->crmObjectType)) {
            throw new Exception('HubSpot object type not set. First set the object type using the `setup` method.');
        }

        // make sure we have properties
        if (empty($this->properties)) {
            throw new Exception('HubSpot object properties not set. First set the properties using the `setup` method.');
        }

        // set the final create properties
        $properties = array_merge($this->properties, $this->activeRules);

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    $this->crmObject = $this->client->crm()->contacts()->basicApi()->create(
                        new ContactSimplePublicObjectInput(
                            ['properties' => $properties]
                        )
                    );
                } catch (ContactApiException $ex) {
                    $this->logger->errorHigh('Error creating contact: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error creating contact: ' . $e->getMessage());
                }
                // done, break out
                break;

            case 'company':
                try {
                    $this->crmObject = $this->client->crm()->companies()->basicApi()->create(
                        new CompanySimplePublicObjectInput(
                            ['properties' => $properties]
                        )
                    );
                } catch (CompanyApiException $ex) {
                    $this->logger->errorHigh('Error creating company: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error creating company: ' . $e->getMessage());
                }
                // done, break out
                break;

            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        $this->logger->infoLow('Record created.');

        // all seems good
        return $this;
    }

    /**
     * Update a record in the HubSpot API
     *
     * @return $this
     */
    public function update()
    {
        $this->logger->infoMid('Updating record...');

        // make sure we have a crm object type
        if (empty($this->crmObjectType)) {
            throw new Exception('HubSpot object type not set. First set the object type using the `setup` method.');
        }

        // make sure we have properties
        if (empty($this->properties)) {
            throw new Exception('HubSpot object properties not set. First set the properties using the `setup` method.');
        }

        // make sure the object is loaded
        if (!$this->crmObjectItemLoaded()) {
            throw new Exception('HubSpot object not loaded. First load the object using the `load` method.');
        }

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    $this->crmObjectItem = $this->client->crm()->contacts()->basicApi()->update(
                        $this->crmObjectItem['id'],
                        new ContactSimplePublicObjectInput(
                            ['properties' => $this->properties]
                        )
                    );
                } catch (ContactApiException $ex) {
                    $this->logger->errorHigh('Error updating contact: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error updating contact: ' . $e->getMessage());
                }
                // done, break out
                break;

            case 'company':
                try {
                    $this->crmObjectItem = $this->client->crm()->companies()->basicApi()->update(
                        $this->crmObjectItem['id'],
                        new CompanySimplePublicObjectInput(
                            ['properties' => $this->properties]
                        )
                    );
                } catch (CompanyApiException $ex) {
                    $this->logger->errorHigh('Error updating company: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error updating company: ' . $e->getMessage());
                }
                // done, break out
                break;
            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        $this->logger->infoLow('Record updated.');

        // all seems good
        return $this;
    }

    /**
     * Delete a record in the HubSpot API
     * Use the delete rules to determine the action to take
     *
     * @param bool $soft Whether to soft delete or hard delete. 'true' for soft delete, 'false' for hard delete
     */
    public function delete($soft = true)
    {
        $this->logger->infoMid('Deleting record...');

        // make sure we have a crm object type
        if (empty($this->crmObjectType)) {
            throw new Exception('HubSpot object type not set. First set the object type using the `setup` method.');
        }

        // make sure we have the delete rules set for what we want to do
        $softDeleteRule = $this->deleteRules['soft_delete'] ?? null;
        $hardDeleteRule = $this->deleteRules['hard_delete'] ?? null;

        // seems like we need to soft delete here
        if ($soft) {
            // check if soft delete details were provided
            if (empty($softDeleteRule)) {
                throw new Exception('HubSpot object soft delete rule not set. First set the delete rules using the `setup` method. If disabled, set the `soft_delete` rule to `false`.');
            }
            // check if soft delete is disabled
            if ($softDeleteRule === false) {
                $this->logger->infoLow('Soft delete rule is disabled. Skipping soft delete here.');
                // all seems good
                return $this;
            }
        }

        // seems like we need to hard delete (aka archive) here
        if (!$soft) {
            // check if hard delete details were provided
            if (empty($hardDeleteRule)) {
                throw new Exception('HubSpot object hard delete rule not set. First set the delete rules using the `setup` method. If disabled, set the `hard_delete` rule to `false`.');
            }
            // check if hard delete is disabled
            if ($hardDeleteRule === false) {
                $this->logger->infoLow('Hard delete rule is disabled. Skipping hard delete here.');
                // all seems good
                return $this;
            }
        }

        // make sure the object is loaded
        if (!$this->crmObjectItemLoaded()) {
            throw new Exception('HubSpot object not loaded. First load the object using the `load` method.');
        }

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    // perform soft-delete update action
                    if ($soft) {
                        $this->crmObjectItem = $this->client->crm()->contacts()->basicApi()->update(
                            $this->crmObjectItem['id'],
                            new ContactSimplePublicObjectInput(['properties' => $softDeleteRule])
                        );
                    } else {
                        // perform hard-delete action
                        $this->client->crm()->contacts()->basicApi()->archive($this->crmObjectItem['id']);
                    }
                } catch (ContactApiException $ex) {
                    $this->logger->errorHigh('Error updating contact: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error updating contact: ' . $e->getMessage());
                }
                // done, break out
                break;

            case 'company':
                try {
                    // perform soft-delete update action
                    if ($soft) {
                        $this->crmObjectItem = $this->client->crm()->companies()->basicApi()->update(
                            $this->crmObjectItem['id'],
                            new CompanySimplePublicObjectInput(['properties' => $softDeleteRule])
                        );
                    } else {
                        // perform hard-delete action
                        $this->client->crm()->companies()->basicApi()->archive($this->crmObjectItem['id']);
                    }
                } catch (CompanyApiException $ex) {
                    $this->logger->errorHigh('Error updating company: ' . $ex->getMessage());
                } catch (Exception $e) {
                    $this->logger->errorHigh('Error updating company: ' . $e->getMessage());
                }
                // done, break out
                break;
            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        $this->logger->infoLow('Record deleted. Method: ' . ($soft ? 'soft' : 'hard'));

        // all seems good
        return $this;
    }

    /**
     * Load, compare and sync the model associations to the CRM
     *
     * @var string $toObjectType The object type to associate to. Options: contacts, companies, deals, tickets
     * @var string $toObjectId The object id to associate to (PK)
     * @var array $associationSpec The array containing association specification. Example: [['association_type_id' => 1, 'association_category' => 'HUBSPOT_DEFINED', [... ]]
     */
    public function associate(string $toObjectType, string $toObjectId, array $associationSpec = [])
    {
        $this->logger->infoMid('Process Associations...', [], $toObjectType);

        // make sure we have an object type
        if (empty($toObjectType)) {
            throw new Exception('HubSpot object type not set.');
        }

        // make sure we have an object id
        if (empty($toObjectId)) {
            throw new Exception('HubSpot object id not set.');
        }

        // make sure the object is loaded
        if (!$this->crmObjectItemLoaded()) {
            throw new Exception('HubSpot object not loaded. First load the object setup the `load` method.');
        }

        // make sure we have a crm object type
        if (empty($this->crmObjectType)) {
            throw new Exception('HubSpot object type not set. First set the object type using the `setup` method.');
        }

        // make sure we have association rules set, if not; simply return the object and log a note
        if (empty($associationSpec)) {
            $this->logger->infoMid('No association rules set. Skipping association process.', [], $toObjectType);
            return $this;
        }

        // good to proceed with the api, we have what we need
        try {
            // ------------------------------
            // load the current object associations
            $currentAssociations = $this->client
                ->crm()
                ->associations()
                ->v4()
                ->basicApi()
                ->getPage(
                    $this->crmObjectType,
                    $this->crmObjectItem['id'],
                    $toObjectType,
                    500
                );

            // ------------------------------
            // loop through the current associations defined in $currentAssociations and compare with the $associationSpec; any missing associations will be created and any extra associations will be removed
            // check if the association exists in the current associations
            $assocExists = false;
            $nonExistingObjectIds = [];

            // only action if we have associated objects
            if (!empty($currentAssociations['results'])) {
                // loop through the current associations
                foreach ($currentAssociations['results'] as $currentAssoc) {
                    // check if one of the associations match the the current object id
                    if ($currentAssoc['to_object_id'] == $toObjectId) {
                        // yes it does exists...
                        $assocExists = true;

                        // check if the association specs match
                        if (!$this->matchAssociationSpecs($associationSpec, $currentAssoc['association_types'])) {
                            $this->logger->infoLow('Association specs do not match. Object ID: ' . $toObjectId . '; Type: ' . $toObjectType . '; Required Spec: ' . json_encode($associationSpec) . '; Current Spec: ' . json_encode($currentAssoc['association_types']), [], $toObjectType);

                            $nonExistingObjectIds[] = $currentAssoc['to_object_id'];
                            $assocExists = false;
                            $this->logger->infoLow('The association specs does NOT match. Marked the associations for replacement...', [], $toObjectType);
                        }
                        else {
                            $this->logger->infoLow('The association specs match. Object ID: ' . $toObjectId . '; Type: ' . $toObjectType . '; Association Spec: ' . json_encode($associationSpec), [], $toObjectType);
                        }

                        // all good, continue to next record
                        continue;
                    }

                    // add to the non-existing object ids list - these will have to be deleted
                    $nonExistingObjectIds[] = $currentAssoc['to_object_id'];
                }
            }

            // delete all associations between two records
            if (!empty($nonExistingObjectIds)) {
                foreach ($nonExistingObjectIds as $nonExistingObjectId) {
                    $this->client->crm()->associations()->v4()->basicApi()->archive(
                        $this->crmObjectType,
                        $this->crmObjectItem['id'],
                        $toObjectType,
                        $nonExistingObjectId
                    );
                    $this->logger->infoLow('Association deleted. Object ID: ' . $nonExistingObjectId . '; Type: ' . $toObjectType, [], $toObjectType);
                }
            }
            else {
                $this->logger->infoLow('No associations to delete.', [], $toObjectType);
            }

            // create the association if it does not exist
            if (!$assocExists) {
                // loop the association specs and create the association
                $postSpec = [];
                foreach ($associationSpec as $assocSpec) {
                    $postSpec[] = new AssociationSpec([
                        'association_category' => $assocSpec['association_category'],
                        'association_type_id' => $assocSpec['association_type_id']
                    ]);
                }
                // init the POST create request
                $this->client->crm()->associations()->v4()->basicApi()->create(
                    $this->crmObjectType,
                    $this->crmObjectItem['id'],
                    $toObjectType,
                    $toObjectId,
                    $postSpec
                );
                $this->logger->infoLow('Association created. Object ID: ' . $toObjectId . '; Type: ' . $toObjectType . '; Association Spec: ' . json_encode($associationSpec), [], $toObjectType);
            }
            else {
                $this->logger->infoLow('No associations to create/add.', [], $toObjectType);
            }

            // all done
            $this->logger->infoMid('Associations processed.', [], $toObjectType);
        } catch (AssociationsApiException $ex) {
            $this->logger->errorHigh('Error with object association: ' . $ex->getMessage());
        } catch (Exception $e) {
            $this->logger->errorHigh('Error with object association: ' . $e->getMessage());
        }
    }

    // --------------------------------------------------------------
    // --- helpers
    // --------------------------------------------------------------

    /**
     * Check if the HubSpot CRM object is loaded (has multiple data set)
     *
     * @return bool
     */
    public function crmObjectLoaded()
    {
        return !is_null($this->crmObject);
    }

    /**
     * Check if the HubSpot CRM object item is loaded (has single data set)
     *
     * @return bool
     */
    public function crmObjectItemLoaded()
    {
        return !is_null($this->crmObjectItem);
    }

    /**
     * Flush the HubSpot CRM object
     *
     * @return void
     */
    public function crmObjectFlush()
    {
        $this->crmObject = null;
    }

    /**
     * Flush the HubSpot CRM object item
     *
     * @return void
     */
    public function crmObjectItemFlush()
    {
        $this->crmObjectItem = null;
    }

    /**
     * Get the HubSpot CRM object record total
     *
     * @return int
     */
    public function getCrmObjectTotal()
    {
        if (!$this->crmObjectLoaded()) {
            return 0;
        }
        return $this->crmObject['total'] ?? 0;
    }

    /**
     * Get the HubSpot CRM object results
     *
     * @return array
     */
    public function getCrmObjectResults()
    {
        if (!$this->crmObjectLoaded()) {
            return [];
        }
        return $this->crmObject['results'] ?? [];
    }

    /**
     * Get the HubSpot CRM object first result
     *
     * @return ModelInterface|null
     */
    public function getCrmObjectFirstItem()
    {
        if (!$this->crmObjectLoaded()) {
            return null;
        }
        return $this->crmObject['results'][0] ?? null;
    }

    /**
     * Get the HubSpot CRM object paging
     *
     * @return ModelInterface|null
     */
    public function getCrmObjectPaging()
    {
        if (!$this->crmObjectLoaded()) {
            return null;
        }
        return $this->crmObject['paging'] ?? null;
    }

    /**
     * Match the association specs to see if they are the same
     * Example:
     *  $localSetup = [
     *      ['association_category' => "HUBSPOT_DEFINED", 'association_type_id' => 12],
     *      ['association_category' => "HUBSPOT_DEFINED", 'association_type_id' => 279],
     *  ];
     *
     *  $remoteSetup = [
     *      ['category' => "HUBSPOT_DEFINED", 'type_id' => 1, 'label' => "Primary"],
     *      ['category' => "HUBSPOT_DEFINED", 'type_id' => 279, 'label' => null],
     *  ];
     *
     * @param array $localSetup
     * @param array $remoteSetup
     * @return bool
     */
    private function matchAssociationSpecs(array $localSetup = [], array $remoteSetup = []) {
        // If arrays have different lengths, they cannot match
        if (count($localSetup) !== count($remoteSetup)) {
            return false;
        }

        // Sort both arrays to ensure order doesn't affect comparison
        usort($localSetup, function ($a, $b) {
            return ($a['association_category'] <=> $b['association_category']) ?: ($a['association_type_id'] <=> $b['association_type_id']);
        });
        usort($remoteSetup, function ($a, $b) {
            return ($a['category'] <=> $b['category']) ?: ($a['type_id'] <=> $b['type_id']);
        });

        // Compare each element in the sorted arrays
        foreach ($localSetup as $index => $item1) {
            $item2 = $remoteSetup[$index];

            if ($item1['association_category'] !== $item2['category'] || $item1['association_type_id'] !== $item2['type_id']) {
                return false;
            }
        }

        return true;
    }
}
