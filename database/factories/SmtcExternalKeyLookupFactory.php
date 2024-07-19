<?php

namespace Wazza\SyncModelToCrm\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;

class SmtcExternalKeyLookupFactory extends Factory
{
    protected $model = SmtcExternalKeyLookup::class;

    public function definition()
    {
        return [
            'object_id'         => $this->faker->uuid,
            'object_type'       => 'user',
            'ext_provider'      => 'hubspot',
            'ext_environment'   => 'sandbox',
            'ext_object_id'     => $this->faker->numberBetween(1000000, 9999999),
            'ext_object_type'   => 'contact',
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];
    }
}
