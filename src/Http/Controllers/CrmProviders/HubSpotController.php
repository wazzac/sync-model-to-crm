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

    /**
     * The logger instance
     *
     * @var LogController
     */
    private $logger;

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
        $this->crmObjectType = array_search($model->getTable(), $objectTableMapping);
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

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    $this->crmObject = $this->client->crm()->contacts()->basicApi()->create(
                        new ContactSimplePublicObjectInput(
                            ['properties' => $this->properties]
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
                            ['properties' => $this->properties]
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

        // loaded object properties
        $loadedContent = $this->getCrmObjectItem();
        if (empty($loadedContent)) {
            throw new Exception('HubSpot object not loaded. First load the object using the `load` method.');
        }

        // ------------------------------
        // load the object according to its type
        switch ($this->crmObjectType) {
            case 'contact':
                try {
                    $this->crmObjectItem = $this->client->crm()->contacts()->basicApi()->update(
                        $loadedContent['id'],
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
                        $loadedContent['id'],
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

        // loaded object properties
        $loadedContent = $this->getCrmObjectItem();
        if (empty($loadedContent)) {
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
                            $loadedContent['id'],
                            new ContactSimplePublicObjectInput(['properties' => $softDeleteRule])
                        );
                    }
                    else {
                        // perform hard-delete action
                        $this->client->crm()->contacts()->basicApi()->archive($loadedContent['id']);
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
                            $loadedContent['id'],
                            new CompanySimplePublicObjectInput(['properties' => $softDeleteRule])
                        );
                    }
                    else {
                        // perform hard-delete action
                        $this->client->crm()->companies()->basicApi()->archive($loadedContent['id']);
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

    public function associate()
    {
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
}
