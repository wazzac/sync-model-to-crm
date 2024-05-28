<?php

namespace Wazza\SyncModelToCrm\Http\Controllers\CrmProviders;

use Wazza\SyncModelToCrm\Http\Contracts\CrmControllerInterface;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;
use Wazza\SyncModelToCrm\Http\Controllers\LogController;

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

    /**
     * The HubSpot API client
     *
     * @var \HubSpot\Discovery\Discovery
     */
    public $client;

    /**
     * The HubSpot object properties that will drive the insert/update
     * @var array
     */
    private $properties = [];

    /**
     * The HubSpot CRM object type
     * Options: contacts, companies, deals, tickets
     *
     * @var string
     */
    private $crmObjectType;

    /**
     * The HubSpot CRM object
     * @var array
     */
    private $crmObject;

    /**
     * The HubSpot CRM object item
     * @var ModelInterface
     */
    private $crmObjectItem;

    /**
     * The log identifier to match event sessions
     * @var string
     */
    private $logIdentifier;

    /**
     * The CRM provider environment/s to use (e.g. production, sandbox, etc.)
     *
     * @var string
     */
    private $environment = null;

    /**
     * Connect to the HubSpot API
     *
     * @param string|null $environment The environment to connect to (e.g. production, sandbox, etc.)
     * @param string|null $logIdentifier The log identifier to match event sessions
     * @return self
     * @throws Exception
     */
    public function connect(?string $environment = 'sandbox', ?string $logIdentifier = null)
    {
        // --------------------------------------------------------------
        // set the environment and log identifier
        $this->environment = $environment;
        $this->logIdentifier = $logIdentifier ?? hash('crc32', microtime(true) . rand(10000, 99999));
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Init connection...', $this->logIdentifier);

        // --------------------------------------------------------------
        // load the crm environment configuration
        $envConf = config('sync_modeltocrm.api.providers.hubspot.' . $this->environment, null);
        if (is_null($envConf)) {
            throw new Exception('HubSpot environment configuration not found.');
        }
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Configuration loaded.', $this->logIdentifier);

        // --------------------------------------------------------------
        // load and set the crm connection (return the client object)
        $this->client = \HubSpot\Factory::createWithAccessToken($envConf['access_token']);
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] CRM client (3rd party) connected.', $this->logIdentifier);
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
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Client disconnected.', $this->logIdentifier);
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
            throw new Exception('HubSpot API not connected.');
        }

        // --------------------------------------------------------------
        // load the provider crm table mapping
        $objectTableMapping = config('sync_modeltocrm.api.providers.' . self::PROVIDER . '.object_table_mapping');
        if (empty($objectTableMapping)) {
            throw new Exception('No provider object table mapping found for Hubspot.');
        }
        // search for the object table name in the $objectTableMapping and return the key
        $this->crmObjectType = array_search($model->getTable(), $objectTableMapping);
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Object type set to: `' . $this->crmObjectType . '`.', $this->logIdentifier);

        // --------------------------------------------------------------
        // set the properties
        $this->properties = [];
        foreach ($mapping as $localProperty => $crmProperty) {
            $this->properties[$crmProperty] = $model->{$localProperty};
        }
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Properties set: ' . json_encode($this->properties), $this->logIdentifier);

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
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__MID, '[' . $this->environment . '][' . self::PROVIDER . '] Loading the CRM object with Primary Key: `' . ($crmObjectPrimaryKey ?? 'not set yet') . '` and Filters: `' . json_encode($searchFilters) . '`', $this->logIdentifier);

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
                        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Loading contact by id: ' . $crmObjectPrimaryKey, $this->logIdentifier);
                        // load the contact by contact id
                        $this->crmObjectItem = $this->client->crm()->contacts()->basicApi()->getById(
                            $crmObjectPrimaryKey,
                            implode(",", array_keys($this->properties))
                        );
                    } else {
                        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Searching for contact by filters: ' . json_encode($searchFilters), $this->logIdentifier);
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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error loading contact: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error loading contact: ' . $e->getMessage(), $this->logIdentifier);
                }
                // done, break out
                break;

            case 'company':
                try {
                    // we have a primary key, load the company by id
                    if (!empty($crmObjectPrimaryKey)) {
                        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Loading company by id: ' . $crmObjectPrimaryKey, $this->logIdentifier);
                        // load the company by company id
                        $this->crmObjectItem = $this->client->crm()->companies()->basicApi()->getById(
                            $crmObjectPrimaryKey,
                            implode(",", array_keys($this->properties))
                        );
                    } else {
                        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Searching for company by filters: ' . json_encode($searchFilters), $this->logIdentifier);
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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error loading company: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error loading company: ' . $e->getMessage(), $this->logIdentifier);
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
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__MID, '[' . $this->environment . '][' . self::PROVIDER . '] Create new record...', $this->logIdentifier);

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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error creating contact: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error creating contact: ' . $e->getMessage(), $this->logIdentifier);
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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error creating company: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error creating company: ' . $e->getMessage(), $this->logIdentifier);
                }
                // done, break out
                break;

            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Record created.', $this->logIdentifier);

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
        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__MID, '[' . $this->environment . '][' . self::PROVIDER . '] Updating record...', $this->logIdentifier);

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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error updating contact: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error updating contact: ' . $e->getMessage(), $this->logIdentifier);
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
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error updating company: ' . $ex->getMessage(), $this->logIdentifier);
                } catch (Exception $e) {
                    LogController::log(LogController::TYPE__ERROR, LogController::LEVEL__HIGH, '[' . $this->environment . '][' . self::PROVIDER . '] Error updating company: ' . $e->getMessage(), $this->logIdentifier);
                }
                // done, break out
                break;
            default:
                throw new Exception('HubSpot object type (' . ($this->crmObjectType ?? 'NA') . ') not supported.');
        }

        LogController::log(LogController::TYPE__INFO, LogController::LEVEL__LOW, '[' . $this->environment . '][' . self::PROVIDER . '] Record updated.', $this->logIdentifier);

        // all seems good
        return $this;
    }

    public function delete()
    {
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
