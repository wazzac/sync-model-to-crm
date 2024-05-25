<p align="center">
<a href="https://github.com/wazzac/sync-model-to-crm/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/sync-model-to-crm"></a>
<a href="https://github.com/wazzac/sync-model-to-crm/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/sync-model-to-crm"></a>
<a href="https://github.com/wazzac/sync-model-to-crm/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/sync-model-to-crm"></a>
</p>

# sync-model-to-crm

A library that will can syncronise any database table model properties to an external Crm provider, like HubSpot or Pipedrive

## Overview

...still in development and more information to follow soon.

## Installation

> PHP 8.1 is a minimum requirement for this project.

1. Open terminal and require the package:

    ```bash
    composer require wazza/sync-model-to-crm
    ```

2. Navigate to the `config` directory within your Laravel project and open the `app.php` file.

    - Look for the `providers` array within the `app.php` file.
    - Inside the `providers` array, add the following line: `Wazza\SyncModelToCrm\Providers\SyncModelToCrmServiceProvider::class,` see example below:

    ```php
    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */
        Wazza\SyncModelToCrm\Providers\SyncModelToCrmServiceProvider::class, // <-- here
        Wazza\DomTranslate\Providers\DomTranslateServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    ```

3. In your terminal again, complete the setup by running teh below commands:

    ```bash
    php artisan vendor:publish --tag="sync-modeltocrm-config"
    php artisan vendor:publish --tag="sync-modeltocrm-migrations"
    php artisan migrate
    ```

4. Below are some of the environment keys that can be added to your _.env_ configuration. If you need more information what each item does, refer to the `config/sync_modeltocrm.php` config file.
    ```
    SYNC_MODEL_TO_CRM_HASH_SALT=Ey4cw2BHvi0HmGYjyqYr
    SYNC_MODEL_TO_CRM_HASH_ALGO=sha256
    SYNC_MODEL_TO_CRM_LOG_INDICATOR=sync-modeltocrm
    SYNC_MODEL_TO_CRM_LOG_LEVEL=3
    SYNC_MODEL_TO_CRM_PROVIDER=hubspot
    SYNC_MODEL_TO_CRM_ENVIRONMENT=sandbox
    SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_URI=https://api.hubapi.com/crm/v4/
    SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_TOKEN=xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    ```

5. Run `config:cache` after you have made your env changes.

    ```bash
    php artisan config:cache
    ```

6. **Done**. Review any configuration file changes that you might want to change. The config file was published to the main config folder.
