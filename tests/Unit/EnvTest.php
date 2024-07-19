<?php

it('should have the correct environment variables set', function () {
    expect(config('app.env'))->toBe('testing');
    expect(env('SYNC_MODEL_TO_CRM_HASH_SALT'))->toBe('Ey4cw2BHvi0HmGYjyqYr');
    expect(env('SYNC_MODEL_TO_CRM_HASH_ALGO'))->toBe('sha256');
    expect(env('SYNC_MODEL_TO_CRM_LOG_INDICATOR'))->toBe('sync-modeltocrm');
    expect(env('SYNC_MODEL_TO_CRM_LOG_LEVEL'))->toBe('3');
    expect(env('SYNC_MODEL_TO_CRM_PROVIDER'))->toBe('hubspot');
    expect(env('SYNC_MODEL_TO_CRM_ENVIRONMENT'))->toBe('sandbox');
    expect(env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_URI'))->toBe('https://api.hubapi.com/crm/v4/');
    // expect(env('SYNC_MODEL_TO_CRM_PROVIDER_HUBSPOT_SANDBOX_TOKEN'))->toBe('do_not_include_in_testing');
});
