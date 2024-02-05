<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Illuminate\Support\Facades\Log;

final class LogController
{
    /**
     * Static Log Controller
     *
     * @param string $type The type of logging i.e. alert, critical, debug, emergency, error, info, notice, warning
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context
     * @return void
     */
    public static function log(string $type, int $level = 1, string $string = "", array $context = [])
    {
        // load the config
        $logConf = config('sync_modeltocrm.logging');

        // make sure we can log
        if (isset($logConf['level']) && !empty($logConf['level']) && $level <= $logConf['level'] && $level > 0) {
            // make sure the method is allowed
            if (in_array($type, ['alert', 'critical', 'debug', 'emergency', 'error', 'info', 'notice', 'warning'])) {
                // log...
                Log::$type('[' . $logConf['indicator'] . '] ' . $string, $context);
            }
        }

        // done...
    }
}
