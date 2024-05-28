<?php

namespace Wazza\SyncModelToCrm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\PhraseFactory;

class SmtcExternalKeyLookup extends Model
{
    use HasFactory;

    //public static function newFactory()
    //{
    //    return PhraseFactory::new();
    //}

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
}
