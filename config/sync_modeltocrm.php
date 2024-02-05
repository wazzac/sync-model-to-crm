<?php

return [
    // Determines the level of logging.
    // For production environments, we recommend using either 0 or 1.
    // `level` ......: 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
    // `indicator` ..: Log indicator used to locate specific items in the log file.
    // ------------------------------------------------------------
    'logging' => [
        'level' => env('SYNC_MODEL_TO_CRM_LOG_LEVEL', 3),
        'indicator' => env('SYNC_MODEL_TO_CRM_LOG_INDICATOR', 'sync-modeltocrm'),
    ],

    // CRM API configuration
    // ------------------------------------------------------------
    'api' => [
        'provider' => env('SYNC_MODEL_TO_CRM_PROVIDER', 'hubspot'), // the provider to use
        'environment' => env('SYNC_MODEL_TO_CRM_ENVIRONMENT', 'production'), // the environment to use
        'hubspot' => [
            'controller' => "Wazza\SyncModelToCrm\Http\Controllers\CrmProviders\HubSpotController",
            'production' => [
                'baseuri'       => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_URI', 'https://api.hubapi.com/crm/v4/'),
                'access_token'  => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_TOKEN', 'xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
            ],
            'sandbox' => [
                'baseuri'       => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_URI', 'https://api.hubapi.com/crm/v4/'),
                'access_token'  => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_TOKEN', 'xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
            ],
        ],
        // more providers to follow...
        'pipedrive' => null,
        'salesforce' => null,
        'zohocrm' => null,
    ],

    // Hash salt and algo settings
    // ------------------------------------------------------------
    'hash' => [
        'salt' => env('SYNC_MODEL_TO_CRM_HASH_SALT', 'Ey4cw2BHvi0HmGYjyqYr'),
        'algo' => env('SYNC_MODEL_TO_CRM_HASH_ALGO', 'sha256'),
    ],
];
