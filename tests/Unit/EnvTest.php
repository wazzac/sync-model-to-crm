<?php

it('should have the correct environment variables set', function () {
    expect(config('app.env'))->toBe('testing');
    expect(config('sync_modeltocrm.db.primary_key_format'))->toBe('int');
    expect(config('sync_modeltocrm.hash.salt'))->toBe('Ey4cw2BHvi0HmGYjyqYr');
    expect(config('sync_modeltocrm.hash.algo'))->toBe('sha256');
    expect(config('sync_modeltocrm.logging.indicator'))->toBe('sync-modeltocrm');
    expect(config('sync_modeltocrm.logging.level'))->toBe('3');
    expect(config('sync_modeltocrm.api.provider'))->toBe('hubspot');
    expect(config('sync_modeltocrm.api.environment'))->toBe('sandbox');
    expect(config('sync_modeltocrm.api.providers.hubspot.sandbox.baseuri'))->toBe('https://api.hubapi.com/crm/v4/');
    // expect(config('sync_modeltocrm.api.providers.hubspot.sandbox.access_token'))->toBe('do_not_include_in_testing');
});
