<?php

namespace CrypTax\Models;

use CrypTax\Exceptions\InvalidTransactionException;
use CrypTax\Exceptions\TooFewTransactionFields;

use CrypTax\Utils\DateUtils;
use CrypTax\Utils\CryptoInfoUtils;

class Transaction
{
    const PURCHASE = 'purchase';
    const SALE = 'sale';
    const EXPENSE = 'expense';

    const TYPES = [self::PURCHASE, self::SALE, self::EXPENSE];

    const TYPES_IT = [
        self::PURCHASE => 'acquisto',
        self::SALE => 'vendita',
        self::EXPENSE => 'spesa'
    ];

    const TYPES_ALT = [
        self::PURCHASE => 'buy',
        self::SALE => 'sell'
    ];

    const CAPITAL_GAINS = 'capital_gains';
    const AIRDROP = 'airdrop';
    const INTEREST = 'interest';
    const CASHBACK = 'cashback';

    const EARNING_CATEGORIES = [self::AIRDROP, self::INTEREST, self::CASHBACK];

    const EARNING_CATEGORIES_IT = [
        self::CAPITAL_GAINS => 'plusvalenze',
        self::INTEREST => 'interessi'
    ];

    public $id;
    public $date;
    public $type;
    public $ticker;
    public $amount;
    public $value;
    public $exchange;
    public $earningCategory;

    public $timestamp;

    public $used = 0.0;
    protected $purchases = [];

    public function __construct($id, $rawTx) {
        $this->id = $id;

        if (count($rawTx) < 5) {
            throw new TooFewTransactionFields($this->id, count($rawTx));
        }

        $this->setDate($rawTx[0]);
        $this->setType($rawTx[1]);
        $this->setTicker($rawTx[4]);
        $this->setAmount($rawTx[3]);
        $this->setValue($rawTx[2]);
        $this->setExchange($rawTx[5] ?? '');
        $this->setEarningCategory($rawTx[6] ?? '');
    }

    public function incrementUsed($amount) {
        $this->used += $amount;
    }

    public function associatePurchase($transaction, $amount) {
        $this->purchases[] = [
            'transaction' => $transaction,
            'amount' => $amount
        ];
    }

    public function getCapitalGain() {
        if ($this->type === self::PURCHASE) {
            return 0.0;
        }

        return $this->value - $this->getRelativePurchaseCost();
    }

    public function getRelativePurchases() {
        return $this->purchases;
    }

    public function getRelativePurchaseCost() {
        $totalCost = 0.0;

        foreach ($this->purchases AS $purchase) {
            $totalCost += $purchase['transaction']->value / $purchase['transaction']->amount * $purchase['amount'];
        }

        return $totalCost;
    }

    private function setDate($date) {
        $this->date = DateUtils::getDateFromItFormat($date);
        $this->timestamp = strtotime($this->date);

        if ($this->date === '1970-01-01' || $this->date > DateUtils::getToday()) {
            throw new InvalidTransactionException($this->id, 'date', $date);
        }
    }

    private function setType($type) {
        $this->type = strtolower(trim($type));

        if (in_array($this->type, self::TYPES_IT)) {
            $this->type = array_search($this->type, self::TYPES_IT);
        }

        if (in_array($this->type, self::TYPES_ALT)) {
            $this->type = array_search($this->type, self::TYPES_ALT);
        }

        if (!in_array($this->type, self::TYPES)) {
            throw new InvalidTransactionException($this->id, 'type', $type);
        }
    }

    private function setTicker($ticker) {
        $ticker = preg_replace('/\([^)]+\)/', '', $ticker);
        $this->ticker = CryptoInfoUtils::getCryptoTicker(trim($ticker));

        if ($this->ticker === '') {
            throw new InvalidTransactionException($this->id, 'ticker', $ticker);
        }
    }

    private function setAmount($amount) {
        $amount = str_replace(',', '.', $amount);

        if (is_numeric($amount)) {
            $this->amount = floatval($amount);
        } else {
            throw new InvalidTransactionException($this->id, 'amount',  $value);
        }
    }

    private function setValue($value) {
        $value = str_replace(',', '.', $value);
        $value = str_replace(['€', '$', ' '], '', $value);

        if (is_numeric($value) && floatval($value) > 0) {
            $this->value = floatval($value);
        } elseif ((is_numeric($value) && floatval($value) === 0.0 ) || trim($value) === '') {
            $this->value = CryptoInfoUtils::getCryptoPrice($this->ticker, $this->date) * $this->amount;
        } else {
            throw new InvalidTransactionException($this->id, 'value',  $value);
        }
    }

    private function setExchange($exchange) {
        $this->exchange = trim($exchange);
    }

    private function setEarningCategory($category) {
        $this->earningCategory = strtolower(trim($category));

        if ($this->earningCategory === '') {
            $this->earningCategory = null;
            return;
        }

        if (in_array($this->earningCategory, self::EARNING_CATEGORIES_IT)) {
            $this->earningCategory = array_search($this->earningCategory, self::EARNING_CATEGORIES_IT);
        }

        if (!in_array($this->earningCategory, self::EARNING_CATEGORIES)) {
            throw new InvalidTransactionException($this->id, 'earning_category', $category);
        }
    }
}
