<?php

namespace Wazza\SyncModelToCrm\Http\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CrmControllerInterface
{
    /**
     * Connect to the chosen CRM API
     *
     * @param string|null $environment
     * @param string|null $logIdentifier
     */
    public function connect(?string $environment = 'sandbox', ?string $logIdentifier = null);

    /**
     * Flush the API client
     *
     * @return void
     */
    public function disconnect();

    /**
     * Check if the HubSpot API is connected
     *
     * @return bool
     */
    public function connected();

    /**
     * Setup the property mappings for the CRM object
     *
     * @param Model $model
     * @param array $mapping
     * @return void
     */
    public function setup(Model $model, array $mapping);

    /**
     * Get the HubSpot CRM object (contains multiple crm records)
     * format: [
     *  'total' => 0,
     * 'results' => [],
     * 'paging' => []
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
     * Load data from the HubSpot API
     *
     * @param string|null $crmObjectPrimaryKey Best case, the key lookup object primary key (when already mapped)
     * @param array $searchFilters The filters to apply to find the specific crm object
     * @return self
     */
    public function load(string|null $crmObjectPrimaryKey = null, array $searchFilters = []);

    /**
     * Create data in the chosen CRM API
     */
    public function create();

    /**
     * Update data in the chosen CRM API
     */
    public function update();

    /**
     * Delete data in the chosen CRM API
     */
    public function delete($soft = true);

    /**
     * Associate data between different objects in the crm
     */
    public function associate(string $toObjectType, string $toObjectId, array $associationSpec = []);

    // --------------------------------------------------------------
    // --- helpers
    // --------------------------------------------------------------

    /**
     * Check if the CRM object is loaded (has multiple data set)
     *
     * @return bool
     */
    public function crmObjectLoaded();

    /**
     * Check if the CRM object item is loaded (has single data set)
     *
     * @return bool
     */
    public function crmObjectItemLoaded();

    /**
     * Flush the HubSpot CRM object
     *
     * @return void
     */
    public function crmObjectFlush();

    /**
     * Flush the HubSpot CRM object item
     *
     * @return void
     */
    public function crmObjectItemFlush();

    /**
     * Get the CRM object total record count
     *
     * @return int
     */
    public function getCrmObjectTotal();

    /**
     * Get the CRM object results
     *
     * @return array|null
     */
    public function getCrmObjectResults();

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
