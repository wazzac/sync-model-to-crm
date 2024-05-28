<p align="center">
<a href="https://github.com/wazzac/sync-model-to-crm/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/sync-model-to-crm"></a>
<a href="https://github.com/wazzac/sync-model-to-crm/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/sync-model-to-crm"></a>
<a href="https://github.com/wazzac/sync-model-to-crm/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/sync-model-to-crm"></a>
</p>

# sync-model-to-crm

A library that will syncronise any defined database table properties (inside the Model) to an external Crm provider, like [HubSpot](https://www.hubspot.com/), [Pipedrive](https://www.pipedrive.com/en) and more.

## Overview

The idea around this library is to make it very easy for a developer to define inside each applicable Model (like `User`, `Entity`, `Order`, etc.) which properties should syncronize to which CRM Provider and Environment.

After each first time successful sync, the CRM Object primary key will be stored in a mapping table against the local table primary key. This allows for quicker loading times for future changes.

Update your Model with 4 properties that define the rules for 3rd-party CRM synchronization:
- @var string|array|null `$smtcEnvironment`;
- @var array `$smtcPropertyMapping`;
- @var array `$smtcUniqueFilters`;
- @var string `$smtcObject`;

Looking at the below example:
1. the `User` Model will syncronize to both the `Sandbox` and `Production` **HubSpot** environments _($smtcEnvironment)_.
2. It will only syncronize the `name` and `email` properties to the HubSpot corresponding `firstname` and `email` fields _($smtcPropertyMapping)_.
3. When there is no internal mapping yet stored, the CRM record will be uniquely loaded using the `email` property _($smtcUniqueFilters)_.
4. In order for the script to know which remote CRM object relates to the User model, `contact` _($smtcObject)_ have to be defined as the remote item.

```PHP
class User extends Authenticatable
{
    // .
    // ..
    // ... original Model content above.

    // --------------------------------------------------------------
    // Sync Model to CRM
    // --------------------------------------------------------------

    /**
     * The CRM provider environment/s to use (e.g. production, sandbox, etc.)
     * Use an array to sync to multiple environments.
     * `null` will take the default defined value from the config file.
     *
     * @var string|array|null
     */
    public $smtcEnvironment = ['sandbox', 'production'];

    /**
     * Mapping array for local and CRM properties
     *
     * @var array
     */
    public $smtcPropertyMapping = [
        'hubspot' => [
            'name' => 'firstname',
            'email' => 'email',
        ],
    ];

    /**
     * Unique filters for the CRM to locate the record if there is no internal mapping available.
     *
     * @var array
     */
    public $smtcUniqueFilters = [
        'hubspot' => [
            'email' => 'email',
        ],
    ];

    /**
     * The CRM object to sync this model to.
     * This is the CRM object type (e.g. contact, company, deal, etc.)
     *
     * @var string
     */
    public $smtcObject = 'contact';
}
```

## Usage

The are primarily 2 methods that you can use to initiate a Model sync.

Executing `(new CrmController())->setModel($user)->execute();`:
1. Directly in a controller action.
2. Via a Observer. e.g. inside a UserObserver to trigger after a save() event. (see below)
    ```PHP
    /**
     * Handle the User "saved" event.
     *
     */
    public function saved(User $user): void
    {
        echo ('saved...');
        (new CrmController())->setModel($user)->execute();
        echo ('synced...');
    }
    ```
3. Inside an event job. This is a good method to separate the logic from the save event and put the sync in a job queue to be processed shortly after the record has been saved.

## Installation

> PHP 8.1 is a minimum requirement for this project.

1. Open terminal and require the package:

    ```bash
    composer require wazza/sync-model-to-crm
    ```

2. Navigate to the `config` directory within your Laravel project and open the `app.php` file.

    - Look for the `providers` array within the `app.php` file.
    - Inside the `providers` array, add the following line and save: `Wazza\SyncModelToCrm\Providers\SyncModelToCrmServiceProvider::class,` see example below:

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

3. In your terminal again, complete the setup by running the below commands:

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

## Looking at the logs

Depending on your log level (refer to your config file settings: 1:high to 3:low) less or more information will be written to your Laravel log file.

You can track the transactions by running `tail -f {log path}` or even including `grep` with the unique transaction 8 digit code.

## Testing

... more details to follow.
