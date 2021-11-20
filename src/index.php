<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use CrypTax\Controllers\MainController;
use CrypTax\Controllers\WebAppController;
use CrypTax\Exceptions\BaseException;
use CrypTax\Exceptions\InvalidTransactionException;

set_time_limit(0);

try {
    if (!defined('MODE') || MODE === 'private') {
        MainController::run();
    } else {
        WebAppController::run();
    }
} catch (BaseException $e) {
    header('Content-type: application/json');
    echo json_encode($e->toJson(), JSON_PRETTY_PRINT);
}
