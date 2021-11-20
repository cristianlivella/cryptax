<?php

namespace CrypTax\Exceptions;

class InvalidYearException extends BaseException
{
    private $year;

    public function __construct($year) {
        $this->year = $year;

        parent::__construct('Invalid transaction');
    }

    public function __toString() {
        return $this->getShortName() . ': Year ' . $this->year . ' is invalid ';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'year' => $this->year
        ];
    }
}
