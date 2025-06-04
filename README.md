<p align="center">
    <a href="https://github.com/wazzac/sync-model-to-crm/issues"><img alt="GitHub issues" src="https://img.shields.io/github/issues/wazzac/sync-model-to-crm"></a>
    <a href="https://github.com/wazzac/sync-model-to-crm/stargazers"><img alt="GitHub stars" src="https://img.shields.io/github/stars/wazzac/sync-model-to-crm"></a>
    <a href="https://github.com/wazzac/sync-model-to-crm/blob/main/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/wazzac/sync-model-to-crm"></a>
</p>

# Sync Model to CRM

Easily synchronize your Laravel Eloquent models with remote CRM providers such as [HubSpot](https://www.hubspot.com/), [Pipedrive](https://www.pipedrive.com/en), and more. This package allows you to define which model properties and relationships should be kept in sync with external CRM objects, providing a seamless integration experience.

---

## Features

- **Flexible Property Mapping:** Define which model attributes map to CRM fields.
- **Multi-Environment Support:** Sync to multiple CRM environments (e.g., sandbox, production).
- **Relationship Sync:** Automatically associate related models (e.g., User to Company).
- **Customizable Sync Triggers:** Initiate syncs via observers, mutators, or queued jobs.
- **Extensible:** Easily add support for additional CRM providers.

---

## Supported CRMs

- **HubSpot**
    - Sync `User` model to HubSpot `Contact` object.
    - Sync `Entity` model to HubSpot `Company` object.
    - Manage associations between `Contact` and `Company`.
    - (Coming soon) Support for `Deal` and `Ticket` objects.
- **Planned Support**
    - Pipedrive
    - Salesforce
    - Zoho CRM
    - Others

---

## Requirements

- **PHP:** 8.2 or higher
- **Laravel:** 12.x

---

## How It Works

Define a set of properties on your Eloquent models to control how and when they sync with your CRM provider. After the first successful sync, the CRM object’s primary key is stored in a mapping table for efficient future updates.

### Required Model Properties

Add the following properties to your model to enable CRM synchronization:

| Property                       | Type                | Description                                                                                  |
|---------------------------------|---------------------|----------------------------------------------------------------------------------------------|
| `$syncModelCrmEnvironment`      | string/array/null   | CRM environments to sync with (e.g., `['sandbox', 'production']`). Defaults to config value. |
| `$syncModelCrmPropertyMapping`  | array (required)    | Maps local model attributes to CRM fields.                                                   |
| `$syncModelCrmUniqueSearch`     | array (required)    | Defines unique fields for identifying CRM records.                                           |
| `$syncModelCrmRelatedObject`    | string              | CRM object type (e.g., `contact`). Defaults to config mapping.                               |
| `$syncModelCrmDeleteRules`      | array (required)    | Rules for handling deletes (soft/hard) in the CRM.                                          |
| `$syncModelCrmActiveRules`      | array (required)    | Rules for restoring/reactivating records in the CRM.                                         |
| `$syncModelCrmAssociateRules`   | array (optional)    | Defines associations with other CRM objects.                                                 |

---

### Example: User Model

Below is a sample `User` model configured for CRM synchronization:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wazza\SyncModelToCrm\Traits\crmTrait;
use App\Models\Entity;
use Wazza\SyncModelToCrm\Http\Controllers\CrmProviders\HubSpotController;

class User extends Authenticatable
{
        use crmTrait, SoftDeletes;

        protected $fillable = ['name', 'email', 'password'];
        protected $hidden = ['password', 'remember_token'];
        protected $casts = [
                'email_verified_at' => 'datetime',
                'password' => 'hashed',
        ];

        public function entity()
        {
                return $this->belongsTo(Entity::class)->withTrashed();
        }

        // CRM Sync Properties
        public $syncModelCrmEnvironment = ['sandbox'];
        public $syncModelCrmPropertyMapping = [
                'hubspot' => [
                        'name' => 'firstname',
                        'email' => 'email',
                ],
        ];
        public $syncModelCrmUniqueSearch = [
                'hubspot' => [
                        'email' => 'email',
                ],
        ];
        public $syncModelCrmRelatedObject = 'contact';
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
        public $syncModelCrmActiveRules = [
                'hubspot' => [
                        'lifecyclestage' => 'customer',
                        'hs_lead_status' => 'OPEN',
                ],
        ];
        public $syncModelCrmAssociateRules = [
                [
                        'assocMethod' => 'entity',
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

        // Optionally, override save() to trigger sync (or use observers)
        public function save(array $options = [])
        {
                parent::save($options);
                // $this->syncToCrmPatch(); // Uncomment to enable direct sync on save
        }
}
```

---

## Usage

You can trigger a model sync in several ways:

1. **Directly in a Controller:**
     ```php
     (new CrmController())->setModel($user)->execute();
     ```

2. **Using the Trait in a Mutator:**
     Call `$this->syncToCrm()` within your model.

3. **Via an Observer:**
     Register an observer to automatically sync after save, update, delete, or restore events.

     ```php
     // App\Observers\UserObserver.php
     use App\Models\User;
     use Wazza\SyncModelToCrm\Http\Controllers\CrmController;

     class UserObserver
     {
             public function created(User $user): void
             {
                     (new CrmController())
                             ->setModel($user)
                             ->setAttemptCreate()
                             ->execute(true);
             }
             public function updated(User $user): void
             {
                     (new CrmController())
                             ->setModel($user)
                             ->setAttemptUpdate()
                             ->execute(true);
             }
             public function deleted(User $user)
             {
                     (new CrmController())
                             ->setModel($user)
                             ->setAttemptDelete()
                             ->execute();
             }
             public function restored(User $user): void
             {
                     (new CrmController())
                             ->setModel($user)
                             ->setAttemptRestore()
                             ->execute();
             }
     }
     ```

     Register the observer in your `AppServiceProvider`:
     ```php
     public function boot(): void
     {
             \App\Models\User::observe(\App\Observers\UserObserver::class);
     }
     ```

4. **In a Job:**
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
     > *If your package supports Laravel auto-discovery, this step may be optional.*

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
     Adjust the published config file as needed.

---

## Monitoring & Logs

Set your desired log level in the config file (`1` = high, `3` = low verbosity).
Monitor sync activity in your Laravel log file:

```bash
tail -f storage/logs/laravel.log | grep sync-modeltocrm
```

---

## Testing

Run the test suite with:

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
