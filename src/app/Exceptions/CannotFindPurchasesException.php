<?php

namespace CrypTax\Exceptions;

class CannotFindPurchasesException extends BaseException
{
    private $transactionId;
    private $foundAmount;
    private $totalAmount;

    public function __construct($transactionId, $foundAmount, $totalAmount) {
        $this->transactionId = $transactionId;
        $this->foundAmount = $foundAmount;
        $this->totalAmount = $totalAmount;
    }

    public function __toString() {
        return $this->getShortName() . ': Transaction ' . $this->transactionId . ', ' . round($this->foundAmount, 8) . ' found on the total of  ' . round($this->totalAmount);
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'transaction_id' => $this->transactionId,
            'found_amount' => $this->foundAmount,
            'total_amount' => $this->totalAmount
        ];
    }
}
