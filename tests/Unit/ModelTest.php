<?php

use Wazza\SyncModelToCrm\Models\SmtcExternalKeyLookup;

it('can create a new `SmtcExternalKeyLookup` model record', function () {
    $data = [
        'object_id' => 'some-object-id',
        'object_type' => 'order',
        'ext_provider' => 'hubspot',
        'ext_environment' => 'sandbox',
        'ext_object_id' => 'external-object-id',
        'ext_object_type' => 'deal',
    ];

    $model = SmtcExternalKeyLookup::create($data);

    expect($model)->toBeInstanceOf(SmtcExternalKeyLookup::class);
    expect($model->object_id)->toEqual($data['object_id']);
    expect($model->object_type)->toEqual($data['object_type']);
    expect($model->ext_provider)->toEqual($data['ext_provider']);
    expect($model->ext_environment)->toEqual($data['ext_environment']);
    expect($model->ext_object_id)->toEqual($data['ext_object_id']);
    expect($model->ext_object_type)->toEqual($data['ext_object_type']);
});

it('can mass assign data to the SmtcExternalKeyLookup model', function () {
    $data = [
        'object_id' => 'some-object-id',
        'object_type' => 'order',
        'ext_provider' => 'hubspot',
        'ext_environment' => 'sandbox',
        'ext_object_id' => 'external-object-id',
        'ext_object_type' => 'deal',
        'unexpected_data' => 'unexpected', // This should not be assigned
    ];

    $model = new SmtcExternalKeyLookup($data);

    expect($model->object_id)->toEqual($data['object_id']);
    expect($model->object_type)->toEqual($data['object_type']);
    expect($model->ext_provider)->toEqual($data['ext_provider']);
    expect($model->ext_environment)->toEqual($data['ext_environment']);
    expect($model->ext_object_id)->toEqual($data['ext_object_id']);
    expect($model->ext_object_type)->toEqual($data['ext_object_type']);
    expect($model->isDirty('unexpected_data'))->toBeFalse(); // Check unexpected data is not assigned
});

it('can update the SmtcExternalKeyLookup model record', function () {
    $model = SmtcExternalKeyLookup::factory()->create();
    $newExtId = rand(10000000, 9999999);

    $model->update([
        'ext_object_id' => $newExtId,
    ]);

    expect($model->ext_object_id)->toEqual($newExtId);
});
