<?php

namespace CrypTax\Exceptions;

class NegativeBalanceException extends BaseException
{
    private $ticker;
    private $balance;
    private $date;

    public function __construct($ticker, $balance, $date) {
        $this->ticker = $ticker;
        $this->balance = $balance;
        $this->date = $date;
    }

    public function __toString() {
        return $this->getShortName() . ': Negative balance (' . $this->balance . ') for crypto ' . $this->ticker . ($this->date ? (' on ' . $this->date) : '');
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'ticker' => $this->ticker,
            'balance' => $this->balance,
            'date' => $this->date
        ];
    }
}
