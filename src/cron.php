<?php
use CrypTax\Utils\DbUtils;
use CrypTax\Utils\CryptoInfoUtils;


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) {
    die();
}

// delete old report files
foreach (new DirectoryIterator(dirname(__FILE__) . '/tmp') AS $fileInfo) {
    if ($fileInfo->isDot()) {
        continue;
    }

    if ($fileInfo->isFile() && substr($fileInfo->getFilename(), 0, 1) !== '.' && time() - $fileInfo->getCTime() > 60 * 60 * 12) {
        unlink($fileInfo->getRealPath());
    }
}

// update cached prices that expire in the next 60 minutes
define('FAKE_CACHE_EXPIRATION', true);

$expirationTime = time() + (60 * 60);
$stmt = DbUtils::getConnection()->prepare('SELECT date, ticker FROM cache WHERE expiration < ?');
$stmt->bind_param('i', $expirationTime);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

while ($resultArray = $result->fetch_assoc()) {
    CryptoInfoUtils::getCryptoPrice($resultArray['ticker'], $resultArray['date']);
}
