<?php

namespace Wazza\SyncModelToCrm\Http\Contracts;

interface CrmControllerInterface
{
    /**
     * Connect to the chosen CRM API
     */
    public function connect();

    /**
     * Load data from the chosen CRM API
     * Use the mapping table to try and match the local data with the CRM data
     */
    public function load();

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
    public function delete();
}
