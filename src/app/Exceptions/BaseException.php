<?php

namespace CrypTax\Exceptions;

use Exception;

abstract class BaseException extends Exception
{
    protected function getShortName() {
        $path = explode('\\', get_class($this));
        return array_pop($path);
    }
}
