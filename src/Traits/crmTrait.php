<?php

namespace Wazza\SyncModelToCrm\Traits;

use Wazza\SyncModelToCrm\Http\Controllers\CrmController;


trait crmTrait
{
    /**
     *
     * @param string $action Options: array [create, update, delete, restore], or single string action (blank array will default to patch actions... aka insert/update)
     * @param bool $associations Options: true, false (process the associations of the related model)
     * @param string $provider Specific provider to action. `null` will follow the config or model setup guidelines.
     * @param string $environment Specific environment to action. `null` will follow the config or model setup guidelines.
     * @return void
     */
    public function syncToCrm(array|string|null $actions = null, $associations = true, $environment = null, $provider = null)
    {
        /**
         * get the current model the trait is called from
         * @var \Illuminate\Database\Eloquent\Model|null $model
         */
        $model = get_class($this);

        // initiate a crm sync process
        $crmSync = new CrmController(null, $actions);
        $crmSync->setModel($model);
        $crmSync->execute($associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on create
     *
     * @param bool $associations
     * @param mixed $environment
     * @param mixed $provider
     * @return void
     */
    public function syncToCrmCreate($associations = true, $environment = null, $provider = null)
    {
        $this->syncToCrm(CrmController::EXEC_ACTION_CREATE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on update
     *
     * @param bool $associations
     * @param mixed $environment
     * @param mixed $provider
     * @return void
     */
    public function syncToCrmUpdate($associations = true, $environment = null, $provider = null)
    {
        $this->syncToCrm(CrmController::EXEC_ACTION_UPDATE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on delete
     *
     * @param bool $associations
     * @param mixed $environment
     * @param mixed $provider
     * @return void
     */
    public function syncToCrmDelete($associations = true, $environment = null, $provider = null)
    {
        $this->syncToCrm(CrmController::EXEC_ACTION_DELETE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on restore
     *
     * @param bool $associations
     * @param mixed $environment
     * @param mixed $provider
     * @return void
     */
    public function syncToCrmRestore($associations = true, $environment = null, $provider = null)
    {
        $this->syncToCrm(CrmController::EXEC_ACTION_RESTORE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on patch
     *
     * @param bool $associations
     * @param mixed $environment
     * @param mixed $provider
     * @return void
     */
    public function syncToCrmPatch($associations = true, $environment = null, $provider = null)
    {
        $this->syncToCrm([CrmController::EXEC_ACTION_CREATE, CrmController::EXEC_ACTION_UPDATE], $associations, $environment, $provider);
    }
}
