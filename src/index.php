<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use CrypTax\Controllers\MainController;
use CrypTax\Controllers\WebAppController;
use CrypTax\Exceptions\BaseException;
use CrypTax\Exceptions\InvalidTransactionException;

header('Access-Control-Allow-Origin: https://app.cryptax.xyz');
header('Access-Control-Allow-Headers: Content-Type, Cookies');
header('Access-Control-Allow-Credentials: true');

$data = json_decode(file_get_contents("php://input"), true);
if (is_array($data)) {
    $_POST = $_POST + $data;
}

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
