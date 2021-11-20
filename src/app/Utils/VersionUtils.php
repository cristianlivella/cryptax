<?php

namespace CrypTax\Utils;

class VersionUtils
{
    public static function getVersionAndDate() {
        return self::getVersion() . ' ' . self::getVersionDate();
    }

    public static function getVersion() {
        if (file_exists(dirname(__FILE__) . '/../../version.txt')) {
            $version = file_get_contents(dirname(__FILE__) . '/../../version.txt');
            $version = explode(PHP_EOL, $version);
            return $version[0] ?? '';
        }

        return self::tryShellExec('git describe --tags');
    }

    public static function getVersionDate() {
        if (file_exists(dirname(__FILE__) . '/../../version.txt')) {
            $version = file_get_contents(dirname(__FILE__) . '/../../version.txt');
            $version = explode(PHP_EOL, $version);
            return $version[1] ?? '';
        }

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
