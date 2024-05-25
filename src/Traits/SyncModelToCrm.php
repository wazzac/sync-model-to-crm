<?php
namespace App\Traits;

trait CrmSyncTrait
{
    /**
     * The CRM provider environment/s to use (e.g. production, sandbox, etc.)
     * Use an array to sync to multiple environments.
     * `null` will take the default from the config file.
     * @var string|array|null
     */
    protected $smtcEnvironment = null;

    /**
     * Mapping array for local and CRM properties
     * Structure:
     *   $smtcPropertyMapping = [
     *     'provider' => [
     *       'local_property' => 'crm_property',
     *       'local_property' => 'crm_property',
     *     ], ...
     *   ];
     *
     * @var array
     */
    protected $smtcPropertyMapping = [];


    public function syncWithCrm()
    {
        // Implement the logic for syncing with CRM using $this->crmMapping
    }

    protected function searchInCrm()
    {
        // Implement the logic for searching in CRM
    }

    protected function updateInCrm()
    {
        // Implement the logic for updating in CRM
    }

    protected function insertInCrm()
    {
        // Implement the logic for inserting in CRM
    }
}
