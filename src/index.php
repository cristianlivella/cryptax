<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use CrypTax\Controllers\MainController;
use CrypTax\Exceptions\InvalidTransactionException;

set_time_limit(0);

MainController::run();
