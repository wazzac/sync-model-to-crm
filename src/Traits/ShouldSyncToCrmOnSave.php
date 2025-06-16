<?php

namespace Wazza\SyncModelToCrm\Traits;

use Wazza\SyncModelToCrm\Http\Controllers\CrmSyncController;

/**
 * Include this trait in your model to enable automatic CRM synchronization on save.
 *
 * This trait listens for the saved event and triggers a CRM sync operation.
 * You do NOT need to override the save() method or manually call the sync method.
 * The sync operation is handled automatically via model events by this trait.
 *
 * IMPORTANT: You do not have to include the `HasCrmSync` in your model if you are using this trait.
 */
trait ShouldSyncToCrmOnSave
{
    /**
     * Boot the trait and add a saved event listener to trigger CRM sync.
     */
    public static function bootShouldSyncOnSaveTrait()
    {
        static::saved(function ($model) {
            $crmSync = app(CrmSyncController::class);
            $crmSync->setModel($model);
            $crmSync->execute();
        });
    }
}
