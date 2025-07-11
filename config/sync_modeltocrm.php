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
        'provider' => env('SYNC_MODEL_TO_CRM_PROVIDER', 'hubspot'), // the default provider to use if not defined in the model
        'environment' => env('SYNC_MODEL_TO_CRM_ENVIRONMENT', 'production'), // the default environment to use if not defined in the model
        'providers' => [
            'hubspot' => [
                'controller' => "Wazza\SyncModelToCrm\Http\Controllers\CrmProviders\HubSpotController",
                'object_table_mapping' => [
                    'contact'   => 'user',
                    'company'   => 'entity',
                    'deal'      => 'order',
                    'product'   => 'product',
                    'line_item' => 'order_item',
                    //... more to follow
                ],
                'production' => [
                    'baseuri'       => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_PRODUCTION_URI', 'https://api.hubapi.com/crm/v4/'),
                    'access_token'  => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_PRODUCTION_TOKEN', 'xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                ],
                'sandbox' => [
                    'baseuri'       => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_URI', 'https://api.hubapi.com/crm/v4/'),
                    'access_token'  => env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_TOKEN', 'xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                ],
            ],
            // more providers to follow...
            'pipedrive' => null,
            'salesforce' => null,
            'zohocrm' => null,
        ]
    ],

    // Hash salt and algo settings
    // ------------------------------------------------------------
    'hash' => [
        'salt' => env('SYNC_MODEL_TO_CRM_HASH_SALT', 'Ey4cw2BHvi0HmGYjyqYr'),
        'algo' => env('SYNC_MODEL_TO_CRM_HASH_ALGO', 'sha256'),
    ],

    // Local database Primary key format
    // Options: 'int' (default) or 'uuid'
    // ------------------------------------------------------------
    'db' => [
        'primary_key_format' => env('SYNC_MODEL_TO_CRM_DB_PRIMARY_KEY_FORMAT', 'int'), // int or uuid (36)
    ],
];
