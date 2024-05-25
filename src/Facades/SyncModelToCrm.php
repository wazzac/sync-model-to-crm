<?php

namespace Wazza\SyncModelToCrm\Facades;

use Illuminate\Support\Facades\Facade;

class SyncModelToCrm extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'sync-model-to-crm';
    }
}
