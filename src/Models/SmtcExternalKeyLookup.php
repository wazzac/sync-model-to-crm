<?php

namespace Wazza\SyncModelToCrm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
     * Get the validation rules that apply based on the migration schema.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        // load the local db primary key format
        $dbPrimaryKeyFormat = config('sync_modeltocrm.db.primary_key_format', 'int');

        // define the validation rules
        return [
            'object_type' => ['nullable', 'string', 'max:64'],
            'object_id' => ['nullable', $dbPrimaryKeyFormat === 'uuid' ? 'string' : 'numeric'],
            'ext_provider' => ['nullable', 'string', 'max:64'],
            'ext_object_type' => ['nullable', 'string', 'max:64'],
            'ext_object_id' => ['nullable', 'string', 'max:128'],
            'ext_environment' => ['nullable', 'string', 'max:32'],
        ];
    }

    // --------------------------------------------
    // Helpers
    // --------------------------------------------

    /**
     * Get the lookup object by the internal object type and id.
     * @return ?self
     */
    public static function viaObject(
        string $type,
        string $id
    ): ?self {
        return self::where('object_type', $type)->where('object_id', $id)->first();
    }

    /**
     * Get the lookup object by the external provider, type, id, and environment.
     * @param string $extProvider The external provider (e.g., 'hubspot').
     * @param string $extObjectType The external object type (e.g., 'contact').
     * @param string $extObjectId The external object ID.
     * @param string $extEnvironment The external environment (defaults to 'production').
     * @return ?self
     */
    public static function viaForeign(
        string $extProvider,
        string $extObjectType,
        string $extObjectId,
        string $extEnvironment = 'production'
    ): ?self {
        return self::where('ext_provider', $extProvider)
            ->where('ext_object_type', $extObjectType)
            ->where('ext_object_id', $extObjectId)
            ->where('ext_environment', $extEnvironment)
            ->first();
    }

    /**
     * Get the lookup object by the internal object type and id, or create it if it doesn't exist.
     * @param string $objecType The local object type.
     * @param string $objectId The local object ID.
     * @param string $extProvider The external provider.
     * @param string $extObjectType The external object type.
     * @param string $extObjectId The external object ID.
     * @param string|null $extEnvironment The external environment (optional, defaults in DB).
     * @return self
     */
    public static function viaObjectCreate(
        string $objecType,
        string $objectId,
        string $extProvider,
        string $extObjectType,
        string $extObjectId,
        string $extEnvironment = 'production'
    ): self {
        // load the record or create it
        return self::firstOrCreate([
            'object_type' => $objecType,
            'object_id' => $objectId
        ], [
            'ext_provider' => $extProvider,
            'ext_object_type' => $extObjectType,
            'ext_object_id' => $extObjectId,
            'ext_environment' => $extEnvironment
        ]);
    }

    /**
     * Get the lookup object by the external provider, type, id, and environment, or create it if it doesn't exist.
     * @param string $extProvider The external provider.
     * @param string $extObjectType The external object type.
     * @param string $extObjectId The external object ID.
     * @param string $objecType The local object type.
     * @param string $objectId The local object ID.
     * @param string $extEnvironment The external environment (defaults to 'production').
     * @return self
     */
    public static function viaForeignCreate(
        string $extProvider,
        string $extObjectType,
        string $extObjectId,
        string $objecType,
        string $objectId,
        string $extEnvironment = 'production'
    ): self {
        // load the record or create it
        return self::firstOrCreate(
            [
                'ext_provider' => $extProvider,
                'ext_object_type' => $extObjectType,
                'ext_object_id' => $extObjectId,
                'ext_environment' => $extEnvironment
            ],
            [
                'object_type' => $objecType,
                'object_id' => $objectId,
            ]
        );
    }

    // ------------------------------------------
    // Query Scopes
    // ------------------------------------------

    /**
     * Scope a query to only include records matching the internal object type and id.
     *
     * @param Builder $query
     * @param string $type
     * @param string $id
     * @return Builder
     */
    public function scopeObject(Builder $query, string $type, string $id): Builder
    {
        return $query->where('object_type', $type)->where('object_id', $id);
    }

    /**
     * Scope a query to only include records matching the external provider, type, id, and environment.
     *
     * @param Builder $query
     * @param string $extProvider The external provider (e.g., 'hubspot').
     * @param string $extObjectType The external object type (e.g., 'contact').
     * @param string $extObjectId The external object ID.
     * @param string|null $extEnvironment The external environment (optional).
     * @return Builder
     */
    public function scopeForeign(Builder $query, string $extProvider, string $extObjectType, string $extObjectId, string $extEnvironment = 'production'): Builder
    {
        return $query->where('ext_provider', $extProvider)
            ->where('ext_object_type', $extObjectType)
            ->where('ext_object_id', $extObjectId)
            ->where('ext_environment', $extEnvironment);
    }

    /**
     * Find or create/update the external key lookup table entry using updateOrCreate.
     *
     * @param string|null $objectId The local model primary key.
     * @param string|null $objecType The local model class name.
     * @param string|null $extProvider The external provider name.
     * @param string|null $extEnvironment The external environment name. Defaults to 'production' if null.
     * @param string|null $extObjectType The external object type.
     * @param string|null $extObjectId The external object primary key.
     * @return self
     */
    public static function updateKeyLookup(
        string|null $objectId = null,
        string|null $objecType = null,
        string|null $extProvider = null,
        string|null $extEnvironment = null,
        string|null $extObjectType = null,
        string|null $extObjectId = null
    ): self {
        return self::updateOrCreate([
            'object_type' => $objecType,
            'object_id' => $objectId,
            'ext_provider' => $extProvider,
            'ext_object_type' => $extObjectType,
            'ext_environment' => $extEnvironment ?? 'production',
        ], [
            'ext_object_id' => $extObjectId,
        ]);
    }
}
