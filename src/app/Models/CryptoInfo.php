<?php

namespace CrypTax\Models;

use CrypTax\Helpers\DateHelper;
use CrypTax\Helpers\CryptoInfoHelper;

class CryptoInfo
{
    /**
     * Cryptocurrency ticker.
     *
     * @var string
     */
    private $ticker;

    /**
     * Cryptocurrency name.
     *
     * @var string
     */
    private $name;

    /**
     * Price on 01/01 of the selected fiscal year.
     *
     * @var float
     */
    private $priceStartOfYear;

    /**
     * Price on 31/12 of the selected fiscal year.
     *
     * @var float
     */
    private $priceEndOfYear;

    /**
     * Current cryptocurrency balance.
     *
     * @var float
     */
    private $balance = 0.0;

    /**
     * Cryptocurrency balance on 01/01 of the selected fiscal year.
     *
     * @var float
     */
    private $balanceStartOfYear = 0.0;

    /**
     * Sum of the daily balances. Used to calculate the average balance.
     *
     * @var float
     */
    private $sumDailyBalances = 0.0;

    /**
     * Max balance in the selected fiscal year.
     *
     * @var float
     */
    private $maxBalance = 0.0;

    /**
     * Day of the fiscal year up to where the processing was done, 0-365.
     *
     * @var integer
     */
    private $currentDayOfYear = 0;

    /**
     * Balance of each day in the fiscal year.
     *
     * @var integer
     */
    private $dailyBalances = [];

    /**
     * Current report fiscal year.
     *
     * @var integer
     */
    private $fiscalYear;

    public function __construct($ticker, $fiscalYear) {
        $this->ticker = CryptoInfoHelper::getCryptoTicker($ticker);
        $this->name = CryptoInfoHelper::getCryptoName($ticker);
        $this->priceStartOfYear = CryptoInfoHelper::getCryptoPrice($ticker, $fiscalYear . '01-01');
        $this->priceEndOfYear = CryptoInfoHelper::getCryptoPrice($ticker, $fiscalYear . '-12-31');
        $this->fiscalYear = $fiscalYear;
    }

    public function saveBalanceStartOfYear() {
        $this->balanceStartOfYear = $this->balance;
    }

    /**
     * Set the daily balances to the current ones until reach the specific day of year.
     *
     * @param  integer $day day of the year, 0-365
     * @return void
     */
    public function setBalancesUntilDay($day) {
        while ($this->currentDayOfYear < $day) {
            $this->dailyBalances[$this->currentDayOfYear] = $this->balance;
            $this->currentDayOfYear++;
        }
    }

    public function incrementBalance($amount, $transaction = null) {
        $this->balance += $amount;

        if ($this->balance < 0) {
            throw new NegativeBalanceException($this->ticker, $this->balance, $transaction ? $transaction->date : null);
        }
    }

    public function getPriceStartOfYear() {
        return CryptoInfoHelper::getCryptoPrice($this->ticker, DateHelper::getFirstDayOfYear($this->fiscalYear));
    }

    public function getPriceEndOfYear() {
        $this->setBalancesUntilDay(DateHelper::old_getNumerOfDaysInYear($this->fiscalYear) + 1);
        return CryptoInfoHelper::getCryptoPrice($this->ticker, DateHelper::getLastDayOfYear($this->fiscalYear));
    }

    public function getValueStartOfYear() {
        return $this->getPriceStartOfYear() * $this->balanceStartOfYear;
    }

    public function getValueEndOfYear() {
        return $this->getPriceEndOfYear() * $this->balance;
    }

    public function getAverageValue($priceDate = '12-31') {
        $dailyBalancesSum = array_sum($this->dailyBalances);
        $daysInYear = DateHelper::old_getNumerOfDaysInYear($this->fiscalYear);
        $price = CryptoInfoHelper::getCryptoPrice($this->ticker, $this->fiscalYear . '-' . $priceDate);

        return $dailyBalancesSum / ($daysInYear + 1) * $price;
    }

    public function getMaxValue($priceData = '12-31') {
        $price = CryptoInfoHelper::getCryptoPrice($this->ticker, $this->fiscalYear . '-' . $priceData);

        return max($this->dailyBalances) * $price;
    }

    public function getDailyValuesStartOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-01-01');
    }

    public function getDailyValuesEndOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-12-31');
    }

    public function getDailyValues($priceDate = null) {
        return array_map(function ($balance, $day) use ($priceDate) {
            if ($priceDate === null) {
                $dateToFetch = DateHelper::getDateFromDayOfYear($day, $this->fiscalYear);
            } else {
                $dateToFetch = $priceDate;
            }

            $price = CryptoInfoHelper::getCryptoPrice($this->ticker, $dateToFetch);

            return $balance * $price;
        }, $this->dailyBalances, array_keys($this->dailyBalances));
    }

    public function getInfoForRender() {
        $info = [
            'name' => $this->name,
            'ticker' => $this->ticker,
            'price_start_of_year' => $this->getPriceStartOfYear(),
            'price_end_of_year' => $this->getPriceEndOfYear(),
            'balance_start_of_year' => $this->balanceStartOfYear,
            'balance_end_of_year' => $this->balance,
            'value_start_of_year' => $this->getValueStartOfYear(),
            'value_end_of_year' => $this->getValueEndOfYear(),
            'average_value' => $this->getAverageValue(),
            'max_value' => $this->getMaxValue()
        ];

        $info['balance_start_of_year'] = $this->formatBalance($info['balance_start_of_year'], $info['price_start_of_year']);
        $info['balance_end_of_year'] = $this->formatBalance($info['balance_end_of_year'], $info['price_end_of_year']);

        foreach (['price_start_of_year', 'price_end_of_year'] AS $field) {
            $info[$field] = number_format($info[$field], 3, ',', '.');
        }

        foreach (['value_start_of_year', 'value_end_of_year', 'average_value', 'max_value'] AS $field) {
            $info[$field] = number_format($info[$field], 2, ',', '.');
        }

        return $info;
    }

    private function formatBalance($balance, $price) {
        $digits = 2;

        if ($price > 10 && $balance > 0) {
            $digits = 8;
        }

        return number_format($balance, $digits, ',', '.');
    }

}
