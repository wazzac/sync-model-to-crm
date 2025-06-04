<?php

namespace Wazza\SyncModelToCrm\Traits;

use Wazza\SyncModelToCrm\Http\Controllers\CrmController;

/**
 * Include the below trait in your model to enable CRM sync functionality.
 *
 * public function save(array $options = [])
 * {
 *     parent::save($options);
 *     {{-- lets call the syncModelToCrm method to sync the model to the CRM. --}}
 *     {{-- refer to the trait for all the available methods --}}
 *     $this->syncToCrmPatch(); -- don't use this method if you are using observers
 * }
 */
trait crmTrait
{
    /**
     * Initiate a `Sync Model to CRM` process
     *
     * @param string|array|null $action Options: array [create, update, delete, restore], or single string action (blank array will default to patch actions... aka insert/update)
     * @param bool $associations Options: true, false (process the associations of the related model)
     * @param string|array|null $environment Specific environment to action. `null` will follow the config or model setup guidelines.
     * @param string|array|null $provider Specific provider to action. `null` will follow the config or model setup guidelines.
     * @return void
     */
    public function syncToCrm(
        array|string|null $actions = null,
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        /**
         * Get the current model instance the trait is called from
         * @var \Illuminate\Database\Eloquent\Model $model
         */
        $model = $this;
        if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \Exception('The syncToCrm method can only be called from an Eloquent model instance.');
        }

        // initiate a crm sync process
        $crmSync = new CrmController(null, $actions);
        $crmSync->setModel($model);
        $crmSync->execute($associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on create
     *
     * @param bool $associations
     * @param string|array|null $environment
     * @param string|array|null $provider
     * @return void
     */
    public function syncToCrmCreate(
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        $this->syncToCrm(CrmController::EXEC_ACTION_CREATE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on update
     *
     * @param bool $associations
     * @param string|array|null $environment
     * @param string|array|null $provider
     * @return void
     */
    public function syncToCrmUpdate(
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        $this->syncToCrm(CrmController::EXEC_ACTION_UPDATE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on delete
     *
     * @param bool $associations
     * @param string|array|null $environment
     * @param string|array|null $provider
     * @return void
     */
    public function syncToCrmDelete(
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        $this->syncToCrm(CrmController::EXEC_ACTION_DELETE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on restore
     *
     * @param bool $associations
     * @param string|array|null $environment
     * @param string|array|null $provider
     * @return void
     */
    public function syncToCrmRestore(
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        $this->syncToCrm(CrmController::EXEC_ACTION_RESTORE, $associations, $environment, $provider);
    }

    /**
     * Sync the model to the CRM on patch
     *
     * @param bool $associations
     * @param string|array|null $environment
     * @param string|array|null $provider
     * @return void
     */
    public function syncToCrmPatch(
        bool $associations = true,
        string|array|null $environment = null,
        string|array|null $provider = null
    ): void {
        $this->syncToCrm([CrmController::EXEC_ACTION_CREATE, CrmController::EXEC_ACTION_UPDATE], $associations, $environment, $provider);
    }
}
