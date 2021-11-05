<?php

namespace CrypTax\Exceptions;

class InvalidTransactionException extends BaseException
{
    private $transactionId;
    private $attribute;
    private $value;

    public function __construct($transactionId, $attribute, $value) {
        $this->transactionId = $transactionId;
        $this->attribute = $attribute;
        $this->value = $value;

        parent::__construct('Invalid transaction');
    }

    public function __toString() {
        return $this->getShortName() . ': Transaction ' . $this->transactionId . ', invalid ' . $this->attribute . ' (' . $this->value . ')';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'transaction_id' => $this->transactionId,
            'attribute' => $this->attribute,
            'value' => $this->value
        ];
    }
}
