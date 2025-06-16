<p align="center">
    <a href="https://github.com/wazzac/sync-model-to-crm/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/sync-model-to-crm"></a>
    <a href="https://github.com/wazzac/sync-model-to-crm/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/sync-model-to-crm"></a>
    <a href="https://github.com/wazzac/sync-model-to-crm/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/sync-model-to-crm"></a>
</p>

# Sync Model to CRM

Easily synchronize your Laravel Eloquent models with remote CRM providers such as [HubSpot](https://www.hubspot.com/), [Pipedrive](https://www.pipedrive.com/en), and more. This package allows you to define which model properties and relationships should be kept in sync with external CRM objects, providing a seamless integration experience.

---

## Features

-   **Flexible Property Mapping:** Define which model attributes map to CRM fields.
-   **Multi-Environment Support:** Sync to multiple CRM environments (e.g., sandbox, production).
-   **Relationship Sync:** Automatically associate related models (e.g., User to Company).
-   **Customizable Sync Triggers:** Initiate syncs via observers, mutators, or queued jobs.
-   **Extensible:** Easily add support for additional CRM providers.

---

## Supported CRMs

-   **HubSpot**
    -   Sync `User` model to HubSpot `Contact` object.
    -   Sync `Entity` model to HubSpot `Company` object.
    -   Manage associations between `Contact` and `Company`.
    -   (Coming soon) Support for `Deal` and `Ticket` objects.
-   **Planned Support**
    -   Pipedrive
    -   Salesforce
    -   Zoho CRM
    -   Others

---

## Requirements

-   **PHP:** 8.2 or higher
-   **Laravel:** 12.x

---

## How It Works

Define a set of properties on your Eloquent models to control how and when they sync with your CRM provider. After the first successful sync, the CRM object’s primary key is stored in a mapping table for efficient future updates.

### Required Model Properties

Add the following properties to your model to enable CRM synchronization:

| Property                       | Type              | Description                                                                                  |
| ------------------------------ | ----------------- | -------------------------------------------------------------------------------------------- |
| `$syncModelCrmEnvironment`     | string/array/null | CRM environments to sync with (e.g., `['sandbox', 'production']`). Defaults to config value. |
| `$syncModelCrmPropertyMapping` | array (required)  | Maps local model attributes to CRM fields.                                                   |
| `$syncModelCrmUniqueSearch`    | array (required)  | Defines unique fields for identifying CRM records.                                           |
| `$syncModelCrmRelatedObject`   | string            | CRM object type (e.g., `contact`). Defaults to config mapping.                               |
| `$syncModelCrmDeleteRules`     | array (required)  | Rules for handling deletes (soft/hard) in the CRM.                                           |
| `$syncModelCrmActiveRules`     | array (required)  | Rules for restoring/reactivating records in the CRM.                                         |
| `$syncModelCrmAssociateRules`  | array (optional)  | Defines associations with other CRM objects.                                                 |

---

### Example: User Model

Below is a sample `User` model configured for CRM synchronization:

```php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Entity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wazza\SyncModelToCrm\Http\Controllers\CrmProviders\HubSpotController;
use Wazza\SyncModelToCrm\Traits\HasCrmSync;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // include this if you wish to use the `Mutators function` or
    // $this->syncToCrm() directly as appose to the observer method
    use HasCrmSync;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Function that will be used to return the relationship data
     * @return type
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    // --------------------------------------------------------------
    // Sync Model to CRM
    // --------------------------------------------------------------

    /**
     * The CRM provider environment/s to use (e.g. production, sandbox, etc.)
     * Use an array to sync to multiple environments.
     * `null` (or not defined) will take the default from the config file.
     *
     * @var string|array|null
     */
    public $syncModelCrmEnvironment = ['sandbox']; // ..or ['sandbox','production']

    /**
     * Mapping array for local and CRM properties
     * This will be the primary property used to cycle through the crm providers
     * and properties to sync the model to the CRM.
     * Required - if not provided, the sync will process will be skipped (no Exceptions will be thrown)
     *
     * @var array
     */
    public $syncModelCrmPropertyMapping = [
        'hubspot' => [
            'name' => 'firstname',
            'email' => 'email',
            // ... add all the properties that you would like to sync
        ],
    ];

    /**
     * Unique filters for the CRM to locate the record if there is no internal mapping available.
     * Not required, but strongly encouraged to be configured as to avoid any duplicate record creation in the crm
     *
     * @var array
     */
    public $syncModelCrmUniqueSearch = [
        'hubspot' => [
            'email' => 'email', // this will ensure that the search filter is unique
        ],
    ];

    /**
     * The CRM object to sync this model to.
     * This is the CRM object type (e.g. contact, company, deal, etc.)
     * If this is null or not provided, the `object_table_mapping` key will be used from the config file.
     *
     * @var string
     */
    public $syncModelCrmRelatedObject = 'contact';

    /**
     * The CRM Delete rules to follow.
     * i.e. if Soft-delete is applicable, what should the CRM record be updated to?
     * if Hard-delete is used, the record will be deleted/archived in the CRM.
     *
     * @var array
     */
    public $syncModelCrmDeleteRules = [
        'hard_delete' => [
            'hubspot' => false,
        ],
        'soft_delete' => [
            'hubspot' => [
                'lifecyclestage' => 'other',
                'hs_lead_status' => 'DELETED',
            ],
        ]
    ];

    /**
     * The CRM Active/Restore rules to follow.
     * These will be the rules to follow for any new entries that are not soft-deleted.
     *
     * @var array
     */
    public $syncModelCrmActiveRules = [
        'hubspot' => [
            'lifecyclestage' => 'customer',
            'hs_lead_status' => 'OPEN',
        ],
    ];

    /**
     * The CRM Associations to sync.
     * This is used to associate the model with other CRM objects.
     *
     * @var array
     */
    public $syncModelCrmAssociateRules = [
        [
            'assocMethod'   => 'entity', // App\Models\Entity::class
            'provider' => [
                'hubspot' => [
                    [
                        'association_category' => HubSpotController::ASSOCIATION_CATEGORY__HUBSPOT_DEFINED,
                        'association_type_id' => HubSpotController::ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY_PRIMARY,
                    ],
                    [
                        'association_category' => HubSpotController::ASSOCIATION_CATEGORY__HUBSPOT_DEFINED,
                        'association_type_id' => HubSpotController::ASSOCIATION_TYPE_ID__CONTACT_TO_COMPANY,
                    ],
                ],
            ],
        ],
    ];

    // --------------------------------------------------------------
    // Custom Methods to initiate a sync
    // --------------------------------------------------------------

    /**
     * (1) Register the observer in the AppServiceProvider boot method
     *
     * public function boot(): void
     *  {
     *      // register the observer/s
     *      // ...refer the the template examples in the sync-model-to-crm repo for a observer working copy
     *      \App\Models\User::observe(\App\Observers\UserObserver::class);
     *  }
     */


    /**
     * (2) Mutators function (Laravel 5.4 or above)
     *
     * Laravel provides mutators which are methods that can be defined on a model to modify
     * attributes before they are saved. You can create a custom mutator named save that
     * first calls the original save method using parent::save() and then performs your
     * additional action.
     *
     * @param array $options
     * @return void
     */
    public function save(array $options = [])
    {
        parent::save($options);

        // lets call the syncModelToCrm method to sync the model to the CRM.
        // refer to the trait for all the available methods
        $this->syncToCrmPatch(); // -- disabled as we are currently using the observer method
    }
}
```

---

## Usage

You can trigger a model sync in several ways:

1. **Using the ShouldSyncToCrmOnSave (Automatic Sync on Save):**
   Add the `ShouldSyncToCrmOnSave` to your Eloquent model to automatically trigger a CRM sync every time the model is saved (created or updated). This is the easiest way to ensure your model stays in sync with your CRM provider without writing custom logic.

    ```php
    use Wazza\SyncModelToCrm\Traits\ShouldSyncToCrmOnSave;

    class User extends Authenticatable
    {
        use ShouldSyncToCrmOnSave;
        // ...
    }
    ```

    **When to use:**
    - Use `ShouldSyncToCrmOnSave` if you want your model to always sync to the CRM automatically after every save (create/update), with no extra code required.
    - This is ideal for most use cases where you want seamless, automatic syncing.

2. **Using the HasCrmSync (Manual or Custom Sync):**
   Add the `HasCrmSync` to your model if you want to control exactly when the sync happens. This trait provides methods like `$this->syncToCrm()`, `$this->syncToCrmCreate()`, `$this->syncToCrmUpdate()`, etc., which you can call from mutators, custom methods, or anywhere in your application.

    ```php
    use Wazza\SyncModelToCrm\Traits\HasCrmSync;

    class User extends Authenticatable
    {
        use HasCrmSync;
        // ...
    }

    public function save(array $options = [])
    {
        parent::save($options);
        $this->syncToCrmPatch(); // Manually trigger sync after save
    }
    ```

    **When to use:**
    - Use `HasCrmSync` if you want to trigger syncs only at specific times, or if you need to customize the sync logic (e.g., only sync on certain conditions, or from a controller, observer, or job).
    - This is ideal for advanced use cases or when you want more granular control over syncing.

3. **Directly in a Controller:**

    ```php
    // If CrmSyncController is registered as a singleton in the service container,
    // you should resolve it via dependency injection or the app() helper
    // to ensure you get the singleton instance:
    app(CrmSyncController::class)->setModel($user)->execute();

    // Or, if you're inside a controller or method with dependency injection:
    public function syncUser(CrmSyncController $crmController, User $user)
    {
        $crmController->setModel($user)->execute();
    }
    ```

    **Note:**
    Instantiating with `new CrmSyncController()` will always create a new instance, bypassing the singleton.
    To use the singleton, always resolve it from the container.

4. **Via an Observer:**
   Register an observer to automatically sync after save, update, delete, or restore events.

5. **In a Job:**
   Offload sync logic to a queued job for asynchronous processing.

---

## Installation

1. **Install via Composer:**

    ```bash
    composer require wazza/sync-model-to-crm
    ```

2. **Register the Service Provider (if not auto-discovered):**
   Add to `bootstrap/providers.php`:

    ```php
    return [
            App\Providers\AppServiceProvider::class,
            Wazza\SyncModelToCrm\Providers\SyncModelToCrmServiceProvider::class,
    ];
    ```

    > _If your package supports Laravel auto-discovery, this step may be optional._

3. **Publish Config and Migrations:**

    ```bash
    php artisan vendor:publish --tag="sync-modeltocrm-config"
    php artisan vendor:publish --tag="sync-modeltocrm-migrations"
    php artisan migrate
    ```

4. **Configure Environment Variables:**
   Add the following to your `.env` file (see `config/sync_modeltocrm.php` for details):

    ```
    SYNC_MODEL_TO_CRM_DB_PRIMARY_KEY_FORMAT=int
    SYNC_MODEL_TO_CRM_HASH_SALT=Ey4cw2BHvi0HmGYjyqYr
    SYNC_MODEL_TO_CRM_HASH_ALGO=sha256
    SYNC_MODEL_TO_CRM_LOG_INDICATOR=sync-modeltocrm
    SYNC_MODEL_TO_CRM_LOG_LEVEL=3
    SYNC_MODEL_TO_CRM_PROVIDER=hubspot
    SYNC_MODEL_TO_CRM_ENVIRONMENT=sandbox
    SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_URI=https://api.hubapi.com/crm/v4/
    SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_TOKEN=xxx-xxx-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    ```

5. **Cache Config:**

    ```bash
    php artisan config:clear
    php artisan config:cache
    ```

6. **Review Configuration:**
   Adjust the published config file as needed. You also do not need all of the env settings above, have a look at the config file
   and overwrite any applicable items. Happy coding 😉

---

## Monitoring & Logs

Set your desired log level in the config file (`1` = high, `3` = low verbosity).
Monitor sync activity in your Laravel log file:

```bash
tail -f storage/logs/laravel.log | grep sync-modeltocrm
```

---

## Testing

We have included a few unit tests, please expand if you wish to fork and help expand the functionalities.

```bash
./vendor/bin/pest
```

Example output:

```bash
     PASS  Tests\Unit\EnvTest
    ✓ it should have the correct environment variables set         1.11s

     PASS  Tests\Unit\ExampleTest
    ✓ it contains a successful example unit test                   0.33s

     PASS  Tests\Unit\ModelTest
    ✓ it can create a new SmtcExternalKeyLookup model record       0.31s
    ✓ it can mass assign data to the SmtcExternalKeyLookup model   0.25s
    ✓ it can update the SmtcExternalKeyLookup model record         0.35s

     PASS  Tests\Feature\ExampleTest
    ✓ it contains a successful example feature test                0.24s

    Tests:    6 passed (25 assertions)
    Duration: 3.84s
```

> More tests and features coming soon!

---

## License

This project is open-sourced under the [MIT license](LICENSE).
