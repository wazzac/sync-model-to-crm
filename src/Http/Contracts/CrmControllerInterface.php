<?php

namespace Wazza\SyncModelToCrm\Http\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for the CrmController class.
 * All CRM controllers must implement this interface.
 *
 * List of required properties:
 * public $logger;
 * public $client;
 * private $properties = [];
 * private $deleteRules = [];
 * private $activeRules = [];
 * private $associateRules = [];
 * private $environment = null;
 * private $crmObjectType;
 * private $crmObject;
 * private $crmObjectItem;
 */
interface CrmControllerInterface
{
    /**
     * Connect to the chosen CRM API
     * IMPORTANT: Refer to the Http\Controllers\CrmProviders\HubSpotController class for connect setup instructions (example)
     * This method should init and set:
     * - $this->logger
     * - $this->environment
     * - $this->client
     *
     * @param string|null $environment
     * @param string|null $logIdentifier
     * @return self
     */
    public function connect(?string $environment = 'sandbox', ?string $logIdentifier = null): self;

    /**
     * Flush the API client
     * This method should unset (flush):
     * - $this->client
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Check if the CRM API is connected
     *
     * @return bool
     */
    public function connected(): bool;

    /**
     * Setup the property mappings for the CRM object
     * This method should init and set below properties:
     * - $this->crmObjectType
     * - $this->properties
     * - $this->deleteRules
     * - $this->activeRules
     * - $this->associateRules
     *
     * @param Model $model
     * @param array $mapping
     * @return self
     */
    public function setup(Model $model, array $mapping): self;

    /**
     * Get the HubSpot CRM object (contains multiple crm records)
     * format: [
     *  'total' => 0,
     *  'results' => [],
     *  'paging' => []
     * ]
     *
     * @return array
     */
    public function getCrmObject();

    /**
     * Get the CRM object item (the single crm record)
     *
     * @return ModelInterface|null
     */
    public function getCrmObjectItem();

    /**
     * Load data from the CRM API
     *
     * @param string|null $crmObjectPrimaryKey Best case, the key lookup object primary key (when already mapped)
     * @param array $searchFilters The filters to apply to find the specific crm object
     * @return self
     */
    public function load(?string $crmObjectPrimaryKey = null, array $searchFilters = []): self;

    /**
     * Create data in the chosen CRM API
     */
    public function create(): self;

    /**
     * Update data in the chosen CRM API
     */
    public function update(): self;

    /**
     * Delete data in the chosen CRM API
     * @param bool $soft If true, the delete will be a soft delete (default: true)
     */
    public function delete(bool $soft = true): self;

    /**
     * Associate data between different objects in the crm
     */
    public function associate(string $toObjectType, string $toObjectId, array $associationSpec = []): self;

    // --------------------------------------------------------------
    // --- helpers --------------------------------------------------
    // --------------------------------------------------------------

    /**
     * Check if the CRM object is loaded (has multiple data set)
     *
     * @return bool
     */
    public function crmObjectLoaded(): bool;

    /**
     * Check if the CRM object item is loaded (has single data set)
     *
     * @return bool
     */
    public function crmObjectItemLoaded(): bool;

    /**
     * Flush the CRM CRM object
     *
     * @return void
     */
    public function crmObjectFlush(): void;

    /**
     * Flush the CRM CRM object item
     *
     * @return void
     */
    public function crmObjectItemFlush(): void;

    /**
     * Get the CRM object total record count
     *
     * @return int
     */
    public function getCrmObjectTotal(): int;

    /**
     * Get the CRM object results
     *
     * @return array
     */
    public function getCrmObjectResults(): array;

    /**
     * Get the CRM object first result
     *
     * @return array|null
     */
    public function getCrmObjectFirstItem();

    /**
     * Get the CRM object paging
     *
     * @return array|null
     */
    public function getCrmObjectPaging();
}
