<?php

namespace CrypTax\Helpers;

class VersionHelper
{
    public static function getVersionAndDate() {
        return self::getVersion() . ' ' . self::getVersionDate();
    }

    public static function getVersion() {
        return 'v1.0.0';
        return self::tryShellExec('git describe --tags');
    }

    public static function getVersionDate() {
        return self::tryShellExec('git log -1 --format=%cd --date=short');
    }

    private static function tryShellExec($command) {
        try {
            return shell_exec($command);
        } catch (Exception $e) {
            return '';
        }
    }
}


?>
