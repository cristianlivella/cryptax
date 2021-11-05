<?php

namespace CrypTax\Models;

use CrypTax\Helpers\DateHelper;

use CrypTax\Models\CryptoInfo;

class CryptoInfoBag
{
    /**
     * All cryptocurrencies in the report with their information
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

    public function __construct($transactions, $fiscalYear) {
        $this->fiscalYear = $fiscalYear;

        foreach ($transactions AS $transaction) {
            if (!isset($this->cryptoInfo[$transaction->ticker])) {
                $this->cryptoInfo[$transaction->ticker] = new CryptoInfo($transaction->ticker, $this->fiscalYear);
            }
        }
    }

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

    public function incrementCryptoBalance($ticker, $amount, $transaction = null) {
        $this->cryptoInfo[$ticker]->incrementBalance($amount, $transaction);
    }

    public function decrementCryptoBalance($ticker, $amount, $transaction = null) {
        $this->incrementCryptoBalance($ticker, $amount * -1);
    }

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

    public function getDailyValuesStartOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-01-01');
    }

    public function getDailyValuesEndOfYear() {
        return $this->getDailyValues($this->fiscalYear . '-12-31');
    }

    public function getDailyValues($priceDate = null) {
        $dailyValues = array_fill(0, DateHelper::old_getNumerOfDaysInYear($this->fiscalYear) + 1, 0);

        foreach ($this->cryptoInfo AS $cryptocurrency) {
            foreach ($cryptocurrency->getDailyValues($priceDate) AS $day => $value) {
                $dailyValues[$day] += $value;
            }
        }

        return $dailyValues;
    }

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

    /*
    public function getDailyValues() {
        $totals = [
            'price_start_of_year' => 0.0,
            'price_end_of_year' => 0.0,
            'real_price' => 0.0
        ];

        foreach ($this->cryptoInfo AS $cryptocurrency) {
            $totals['price_start_of_year'] += $cryptocurrency->getDailyValuesStartOfYear();
            $totals['price_end_of_year'] += $cryptocurrency->getDailyValuesEndOfYear();
            $totals['real_price'] += $cryptocurrency->getDailyValues();
        }

        return $totals;
    }
    */

}
