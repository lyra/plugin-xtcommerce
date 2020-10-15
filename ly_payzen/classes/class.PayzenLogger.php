<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for xt:Commerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

class PayzenLogger
{
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;

    private $levels = array(
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARN => 'WARN',
        self::ERROR => 'ERROR'
    );

    const LOG_LEVEL = self::INFO;
    const LOG_PATH = 'xtLogs/payzen.log';

    private $name;
    private $path;

    // Logger single instance.
    private static $logger = null;

    // Logger private constructor.
    private function __construct()
    {
        $this->path = _SRV_WEBROOT. self::LOG_PATH;
    }

    // Create a single instance of logger if it doesn't exist yet.
    public static function getLogger($name)
    {
        if (is_null(self::$logger)) {
            self::$logger = new PayzenLogger();
        }

        self::$logger->name = $name;

        return self::$logger;
    }

    public function log($msg, $msgLevel = self::INFO)
    {
        if ($msgLevel < 1 || $msgLevel > 4) {
            $msgLevel = self::INFO;
        }

        if ($msgLevel < self::LOG_LEVEL) {
            // No logs.
            return;
        }

        $date = date('Y-m-d H:i:s', time());

        $fLog = @fopen($this->path, 'a');
        if ($fLog) {
            fwrite($fLog, "[$date] " . $this->name . ". {$this->levels[$msgLevel]}: $msg\n");
            fclose($fLog);
        }
    }

    public function debug($msg)
    {
        $this->log($msg, self::DEBUG);
    }

    public function info($msg)
    {
        $this->log($msg, self::INFO);
    }

    public function warn($msg)
    {
        $this->log($msg, self::WARN);
    }

    public function error($msg)
    {
        $this->log($msg, self::ERROR);
    }
}
