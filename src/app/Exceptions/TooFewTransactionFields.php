<?php

namespace CrypTax\Exceptions;

class TooFewTransactionFields extends BaseException
{
    const EXPECTED_FIELDS = 7;
    private $transactionId;
    private $fieldsCount;

    public function __construct($transactionId, $fieldsCount) {
        $this->transactionId = $transactionId;
        $this->fieldsCount = $fieldsCount;
    }

    public function __toString() {
        return $this->getShortName() . ': Transaction ' . $this->transactionId . ', ' . $this->fieldsCount . ' fields received, ' . self::EXPECTED_FIELDS . ' expected';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'transaction_id' => $this->transactionId,
            'fields' => $this->fieldsCount,
            'expected' => self::EXPECTED_FIELDS
        ];
    }
}
