<?php

namespace CrypTax\Models;

use CrypTax\Models\CryptoInfo;
use CrypTax\Models\Transaction;
use CrypTax\Utils\DateUtils;

class CryptoInfoBag
{
    /**
     * All cryptocurrencies in the report with their information.
     *
     * @var CryptoInfo[]
     */
    private $cryptoInfo = [];

    /**
     * Set to true after the start of (fiscal) year balances have been saved.
     *
     * @var boolean
     */
    private $startOfYearDataInitialized = false;

    /**
     * Current report fiscal year.
     *
     * @var integer
     */
    private $fiscalYear;

    /**
     * Parse the transactions and initialize the new encountered cryptocurrencies.
     *
     * @param Transaction[] $transactions
     * @param integer $fiscalYear
     */
    public function __construct($transactions, $fiscalYear) {
        $this->fiscalYear = $fiscalYear;

        foreach ($transactions AS $transaction) {
            if (!isset($this->cryptoInfo[$transaction->ticker])) {
                $this->cryptoInfo[$transaction->ticker] = new CryptoInfo($transaction->ticker, $this->fiscalYear);
            }
        }
    }

    /**
     * Snapshot the balances at the beginning of the year, if not already done.
     *
     * @return void
     */
    public function saveStartOfYearSnapshot() {
        if ($this->startOfYearDataInitialized) {
            return;
        }

        foreach ($this->cryptoInfo AS $cryptocurrency) {
            $cryptocurrency->saveBalanceStartOfYear();
        }

        $this->startOfYearDataInitialized = true;
    }

    /**
     * Set the daily balances to the current ones until reach the specific day of year.
     *
     * @param  integer $day day of the year, 0-365
     * @return void
     */
    public function setBalancesUntilDay($day) {
        foreach ($this->cryptoInfo AS $cryptocurrency) {
            $cryptocurrency->setBalancesUntilDay($day);
        }
    }

    /**
     * Increment the balance of a given cryptocurrency.
     *
     * @param string $ticker
     * @param float $amount
     * @param Transaction $transaction
     * @return void
     */
    public function incrementCryptoBalance($ticker, $amount, $transaction = null) {
        $this->cryptoInfo[$ticker]->incrementBalance($amount, $transaction);
    }

    /**
     * Decrement the balance of a given cryptocurrency.
     *
     * @param string $ticker
     * @param float $amount
     * @param Transaction $transaction
     * @return void
     */
    public function decrementCryptoBalance($ticker, $amount, $transaction = null) {
        $this->incrementCryptoBalance($ticker, $amount * -1);
    }

    /**
     * Sort the cryptocurrencies list by their decreasing average value.
     *
     * @return void
     */
    public function sortByAverageValue() {
        usort($this->cryptoInfo, function ($a, $b) {
            $averageA = $a->getAverageValue();
            $averageB = $b->getAverageValue();

            if ($averageA > $averageB) {
                return -1;
            } elseif ($averageA < $averageB) {
                return 1;
            } else {
                return 0;
            }
        });
    }

    /**
     * Get the total daily values using the prices at the beginning of the fiscal year.
     *
     * @return float[]
     */
    public function getDailyValuesStartOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-01-01');
    }

    /**
     * Get the total daily values using the prices at the end of the fiscal year.
     *
     * @return float[]
     */
    public function getDailyValuesEndOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-12-31');
    }

    /**
     * Get the total daily values using the price at the specified date.
     * If priceDate is null, real daily prices are used.
     *
     * @param string $priceDate
     * @return float[]
     */
    public function getDailyValues($priceDate = null) {
        $dailyValues = array_fill(0, DateUtils::old_getNumerOfDaysInYear($this->fiscalYear) + 1, 0);

        foreach ($this->cryptoInfo AS $cryptocurrency) {
            foreach ($cryptocurrency->getDailyValues($priceDate) AS $day => $value) {
                $dailyValues[$day] += $value;
            }
        }

        return $dailyValues;
    }

    /**
     * Get the info used for the report rendering.
     *
     * @return array
     */
    public function getInfoForRender() {
        return array_filter(array_map(function ($cryptocurrency) {
            if ($cryptocurrency->getAverageValue() === 0.0) {
                return null;
            }

            return $cryptocurrency->getInfoForRender();
        }, $this->cryptoInfo), function($crypto) {
            return $crypto !== null;
        });
    }

    /**
     * Get the sum of the value related data.
     *
     * @return array
     */
    public function getTotalValues() {
        $totals = [
            'value_start_of_year' => 0.0,
            'value_end_of_year' => 0.0,
            'average_value' => 0.0,
            'max_value' => 0.0
        ];

        foreach ($this->cryptoInfo AS $cryptocurrency) {
            $totals['value_start_of_year'] += $cryptocurrency->getValueStartOfYear();
            $totals['value_end_of_year'] += $cryptocurrency->getValueEndOfYear();
            $totals['average_value'] += $cryptocurrency->getAverageValue();
            $totals['max_value'] += $cryptocurrency->getMaxValue();
        }

        return $totals;
    }
}
