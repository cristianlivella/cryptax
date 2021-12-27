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
    const TRADE = 'trade';

    const TYPES = [self::PURCHASE, self::SALE, self::EXPENSE, self::TRADE];

    const TYPES_IT = [
        self::PURCHASE => 'acquisto',
        self::SALE => 'vendita',
        self::EXPENSE => 'spesa',
        self::TRADE => 'scambio'
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
    public $baseCurrency;
    public $baseValue;
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

        // TODO: just for testing; need to implement automatic file version recognition
        $version2 = 1;

        $this->setDate($rawTx[0]);
        $this->setType($rawTx[1]);
        $this->setTicker($rawTx[4 + $version2]);
        $this->setAmount($rawTx[3 + $version2]);
        $this->setExchange($rawTx[5 + $version2] ?? '');
        $this->setEarningCategory($rawTx[6 + $version2] ?? '');

        if ($version2) {
            $this->setBaseCurrency($rawTx[3]);
            $this->setBaseValue($rawTx[2]);

            if ($this->baseCurrency === 'EUR' && $this->type === self::TRADE) {
                $this->type = self::PURCHASE;
            } elseif ($this->baseCurrency !== 'EUR' && $this->type !== self::TRADE) {
                if ($this->type === self::SALE) {
                    $this->swapBaseValue();
                } elseif ($this->type === self::EXPENSE) {
                    $this->baseCurrency = null;
                    $this->baseValue = null;
                }

                $this->type = self::TRADE;
            }

            if ($this->type === self::TRADE) {
                $this->value = CryptoInfoUtils::getCryptoPrice($this->ticker, $this->date) * $this->amount;

                if ($this->baseCurrency !== 'EUR' && $this->baseValue > 0) {
                    $this->value += CryptoInfoUtils::getCryptoPrice($this->baseCurrency, $this->date) * $this->baseValue;
                    $this->value /= 2;
                }
            } else {
                $this->setValue($rawTx[2]);
            }

        } else {
            if ($this->type === self::TRADE) {
                throw new InvalidTransactionException($this->id, 'type', $this->type);
            }

            $this->setValue($rawTx[2]);
        }
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

    public function getCapitalGain($withRelatedTradesGains = true) {
        if ($this->type === self::PURCHASE) {
            return 0.0;
        }

        $gains = $this->value - $this->getRelativePurchaseCost();

        if ($withRelatedTradesGains) {
            foreach ($this->purchases AS $purchase) {
                if ($purchase['transaction']->type === self::TRADE) {
                    $gains += $purchase['transaction']->getCapitalGain() / $purchase['transaction']->amount * $purchase['amount'];
                }
            }
        }

        return $gains;
    }

    public function getRelativePurchases() {
        return $this->purchases;
    }

    private function getRelativePurchaseCost() {
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

    private function setBaseCurrency($ticker) {
        $ticker = preg_replace('/\([^)]+\)/', '', $ticker);
        $this->baseCurrency = strtoupper(trim($ticker));

        if ($this->baseCurrency === '' || $this->baseCurrency === 'EUR') {
            $this->baseCurrency = 'EUR';
        } else {
            $this->baseCurrency = CryptoInfoUtils::getCryptoTicker($this->baseCurrency);
        }

        if ($this->ticker === '') {
            throw new InvalidTransactionException($this->id, 'base_currency', $ticker);
        }
    }

    private function setBaseValue($value) {
        $value = str_replace(',', '.', $value);
        $value = str_replace(['€', '$', ' '], '', $value);

        if (is_numeric($value) && floatval($value) >= 0) {
            $this->baseValue = floatval($value);
        } else {
            throw new InvalidTransactionException($this->id, 'base_value',  $value);
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

        if (is_numeric($amount) && floatval($amount) > 0) {
            $this->amount = floatval($amount);
        } else {
            throw new InvalidTransactionException($this->id, 'amount',  $amount);
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

    private function swapBaseValue() {
        $baseValue = $this->baseValue;
        $baseCurrency = $this->baseCurrency;

        $this->baseValue = $this->amount;
        $this->baseCurrency = $this->ticker;

        $this->amount = $baseValue;
        $this->ticker = $baseCurrency;
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
