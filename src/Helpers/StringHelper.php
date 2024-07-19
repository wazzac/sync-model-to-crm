<?php

namespace Wazza\SyncModelToCrm\Helpers;

class StringHelper
{
    /**
     * Hash a given string
     *
     * @param string $string
     * @return string
     */
    public static function hash(string $string)
    {
        return hash_hmac(
            config('dom_translate.hash.algo'),
            $string,
            config('dom_translate.hash.salt')
        );
    }
}
