<?php

namespace Demo\App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Demo\App\Models\Entity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wazza\SyncModelToCrm\Http\Controllers\CrmProviders\HubSpotController;
use Wazza\SyncModelToCrm\Traits\crmTrait;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;
    use crmTrait; // include this if you wish to use the `Mutators function` or $this->syncToCrm() directly as appose to the observer method

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
    public $syncModelCrmEnvironment = ['sandbox'];

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
            'email' => 'email',
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
     * The Crm Delete rules to follow.
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
     * The Crm Active/Restore rules to follow.
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
     * The Crm Associations to sync.
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
        // $this->syncToCrmPatch(); -- disabled as we are currently using the observer method
    }
}
