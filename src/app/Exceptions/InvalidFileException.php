<?php

namespace CrypTax\Exceptions;

class InvalidFileException extends BaseException
{
    public function __construct() {
        parent::__construct();
    }

    public function __toString() {
        return $this->getShortName() . ': The uploaded file is invalid';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName()
        ];
    }
}
