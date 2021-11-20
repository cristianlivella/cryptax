<?php

namespace CrypTax\Exceptions;

class FileTooBigException extends BaseException
{
    public function __construct() {
        parent::__construct();
    }

    public function __toString() {
        return $this->getShortName() . ': The maximum upload file size is 1 MB.';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName()
        ];
    }
}
