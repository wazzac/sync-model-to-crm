<?php

namespace Wazza\SyncModelToCrm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Wazza\SyncModelToCrm\Database\Factories\SmtcExternalKeyLookupFactory;

class SmtcExternalKeyLookup extends Model
{
    use HasFactory;

    public static function newFactory()
    {
        return SmtcExternalKeyLookupFactory::new();
    }

    /**
     * The database table used by the model.
     * @var string
     */
    protected $table = 'smtc_external_key_lookup';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'object_id',
        'object_type',
        'ext_provider',
        'ext_environment',
        'ext_object_id',
        'ext_object_type'
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'object_id' => ['required', 'string', 'max:36'],
            'object_type' => ['required', 'string', 'max:32'],
            'ext_provider' => ['required', 'string', 'max:32'],
            'ext_environment' => ['required', 'string', 'max:16'],
            'ext_object_id' => ['required', 'string', 'max:36'],
            'ext_object_type' => ['required', 'string', 'max:32'],
            // unique combination of object_id, object_type, ext_provider, ext_environment, ext_object_id, ext_object_type
            'unique_combination' => ['required', 'unique:smtc_external_key_lookup,object_id,object_type,ext_provider,ext_environment,ext_object_id,ext_object_type'],
        ];
    }

    /**
     * Update the external key lookup table with the provided values
     *
     * @param string|null $objectId The local model primary key
     * @param string|null $objectType The local model class name.
     * @param string|null $crmProvider The CRM provider name. e.g. 'salesforce', 'hubspot', etc.
     * @param string|null $crmEnvironment The CRM environment name. e.g. 'sandbox', 'production', etc.
     * @param string|null $crmObjectType The CRM object type. e.g. 'contact', 'company', etc.
     * @param string|null $crmObjectId The CRM object primary key. e.g. '1231258465'
     * @return SmtcExternalKeyLookup
     */
    public static function updateKeyLookup(
        string|null $objectId = null,
        string|null $objectType = null,
        string|null $crmProvider = null,
        string|null $crmEnvironment = null,
        string|null $crmObjectType = null,
        string|null $crmObjectId = null
    ) {
        // look in the object mapping table to see if we can find the crm object primary key
        $keyLookup = self::where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->where('ext_provider', $crmProvider)
            ->where('ext_environment', $crmEnvironment)
            ->where('ext_object_type', $crmObjectType)
            ->where('ext_object_id', $crmObjectId)
            ->first();

        // if we found a match, update the existing entry, otherwise create a new entry
        if ($keyLookup) {
            // update the existing entry
            $keyLookup->ext_object_id = $crmObjectId;
            $keyLookup->save();
        } else {
            // create a new entry
            $keyLookup = new self();
            $keyLookup->object_id = $objectId;
            $keyLookup->object_type = $objectType;
            $keyLookup->ext_provider = $crmProvider;
            $keyLookup->ext_environment = $crmEnvironment;
            $keyLookup->ext_object_id = $crmObjectId;
            $keyLookup->ext_object_type = $crmObjectType;
            $keyLookup->save();
        }

        // return the key lookup object
        return $keyLookup;
    }
}
