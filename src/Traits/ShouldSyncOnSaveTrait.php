<?php

namespace Wazza\SyncModelToCrm\Traits;

use Wazza\SyncModelToCrm\Http\Controllers\CrmController;

/**
 * Include this trait in your model to enable automatic CRM synchronization on save.
 *
 * This trait listens for the saved event and triggers a CRM sync operation.
 * You do NOT need to override the save() method or manually call the sync method.
 * The sync operation is handled automatically via model events by this trait.
 *
 * Also no need to include the CrmTrait in your model if you are using this trait.
 */
trait ShouldSyncOnSaveTrait
{
    /**
     * Boot the trait and add a saved event listener to trigger CRM sync.
     */
    public static function bootShouldSyncOnSaveTrait()
    {
        static::saved(function ($model) {
            $crmSync = app(CrmController::class);
            $crmSync->setModel($model);
            $crmSync->execute();
        });
    }
}
