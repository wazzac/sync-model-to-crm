<?php

namespace Wazza\SyncModelToCrm\Helpers;

class StringHelper
{
    /**
     * Hash a given string using the configured hash algorithm and salt.
     *
     * @param string $string
     * @return string
     */
    public static function hash(string $string): string
    {
        return hash_hmac(
            config('dom_translate.hash.algo'),
            $string,
            config('dom_translate.hash.salt')
        );
    }
}
