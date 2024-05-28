<?php

namespace Wazza\SyncModelToCrm\Http\Controllers;

use Illuminate\Support\Facades\Log;

final class LogController
{
    // define the log types
    public const TYPE__ALERT = 'alert';
    public const TYPE__CRITICAL = 'critical';
    public const TYPE__DEBUG = 'debug';
    public const TYPE__EMERGENCY = 'emergency';
    public const TYPE__ERROR = 'error';
    public const TYPE__INFO = 'info';
    public const TYPE__NOTICE = 'notice';
    public const TYPE__WARNING = 'warning';

    // define the log levels
    public const LEVEL__NONE = 0;
    public const LEVEL__HIGH = 1;
    public const LEVEL__MID = 2;
    public const LEVEL__LOW = 3;

    /**
     * Static Log Controller
     *
     * @param string $type The type of logging i.e. alert, critical, debug, emergency, error, info, notice, warning
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param string|null $logIdentifier The log identifier for session tracking - i.e. using grep to find related logs
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @return void
     */
    public static function log(
        string $type,
        int $level = self::LEVEL__HIGH,
        string $string = "",
        string $logIdentifier = null,
        array $context = [],
    ) {
        // load the config
        $logConf = config('sync_modeltocrm.logging');

        // make sure the type is allowed - default to `info`
        if (!in_array($type, ['alert', 'critical', 'debug', 'emergency', 'error', 'info', 'notice', 'warning'])) {
            $type = self::TYPE__INFO;
        }

        // make sure we can log
        if (
            isset($logConf['level']) &&
            !empty($logConf['level']) &&
            $level <= $logConf['level'] && $level > 0
        ) {
            // make sure the method is allowed
            if (in_array($type, ['alert', 'critical', 'debug', 'emergency', 'error', 'info', 'notice', 'warning'])) {
                // log...
                Log::$type('[' . $logConf['indicator'] . ']' . (!is_null($logIdentifier) ? '[' . $logIdentifier . '] ' : ' ') . $string, $context);
            }
        }

        // done...
    }
}
