<?php

namespace App\Tools;

use Throwable;

class Logger
{
    private static ?string $logFile = null;

    public static function log(string $message, string $level = 'INFO'): void
    {
        $logFile = self::getLogFile();
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $date = date('Y-m-d H:i:s');
        $formattedMessage = "[$date] [$level] $message" . PHP_EOL;

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    public static function error(string $message, ?Throwable $exception = null): void
    {
        $details = "";
        if ($exception) {
            $details = sprintf(
                " | Exception: %s | File: %s:%d | Stacktrace: %s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }
        self::log($message . $details, 'ERROR');
    }

    private static function getLogFile(): string
    {
        if (self::$logFile === null) {
            self::$logFile = App::getDataDir() . '/.cache/app.log';
        }
        return self::$logFile;
    }
}
