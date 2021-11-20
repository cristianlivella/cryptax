<?php

// delete old report files

if (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) {
    die();
}

foreach (new DirectoryIterator(dirname(__FILE__) . '/tmp') AS $fileInfo) {
    if ($fileInfo->isDot()) {
        continue;
    }

    if ($fileInfo->isFile() && substr($fileInfo->getFilename(), 0, 1) !== '.' && time() - $fileInfo->getCTime() > 60 * 60 * 12) {
        unlink($fileInfo->getRealPath());
    }
}
