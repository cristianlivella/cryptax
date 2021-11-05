<?php

namespace CrypTax\Models;

use CrypTax\Exceptions\CannotFindPurchasesException;
use CrypTax\Exceptions\InvalidTransactionException;
use CrypTax\Exceptions\InvalidYearException;
use CrypTax\Exceptions\NegativeBalanceException;
use CrypTax\Helpers\CryptoInfoHelper;
use CrypTax\Helpers\DateHelper;
use CrypTax\Models\CryptoInfo;
use CrypTax\Models\Transaction;
use CrypTax\Models\TransactionBag;

use DateTime;

class Report
{
    const CAPITAL_GAINS_NO_TAX_AREA_THRESHOLD = 51645.69;
    const CAPITAL_GAINS_TAX_RATE = 0.26;
    const INTERESTS_EARNING_TAX_RATE = 0.26;

    /**
     * Contains the transactions parsed from the input file.
     *
     * @var TransactionsBag
     */
    private TransactionsBag $transactionsBag;

    /**
     * Contains all cryptocurrencies in the report with their information.
     *
     * @var CryptoInfoBag
     */
    private CryptoInfoBag $cryptoInfoBag;

    /**
     * Contains the details about the various categories of earnings.
     *
     * @var EarningsBag
     */
    private EarningsBag $earningsBag;

    /**
     * Cryptocurrency purchases minus cryptocurrency sales in selected fiscal year.
     *
     * @var float
     */
    private float $currentYearInvestment = 0.0;

    /**
     * Purchase cost related to sales made in the selected fiscal year.
     *
     * @var float
     */
    private float $currentYearPurchaseCost = 0.0;

    /**
     * Incomes from the sale of cryptocurrencies in the selected fiscal year.
     *
     * @var float
     */
    private float $currentYearIncome = 0.0;

    /**
     * Volumes for each exchange.
     * es. $exchangeVolume['Binance'] is the current year volume of Binance.
     *
     * @var float[]
     */
    private array $exchangeVolumes = [];

    /**
     * Selected fiscal year.
     *
     * @var integer
     */
    private int $fiscalYear;

    public function __construct($transactionsFileContent) {
        $this->transactionsBag = new TransactionsBag($transactionsFileContent);
    }

    /**
     * Processes the transactions, calculates capital gains and earnings.
     *
     * @param integer $year
     * @return void
     */
    public function elaborateReport(int $year) {
        $this->setFiscalYear($year);

        $firstDayOfYear = DateHelper::getFirstDayOfYear($this->fiscalYear);
        $lastDayOfYear = DateHelper::getLastDayOfYear($this->fiscalYear);

        foreach ($this->transactionsBag->transactions AS $tx) {
            if ($tx->date > $lastDayOfYear) {
                // Don't elaborate transactions made after the end of the selected fiscal year.
                break;
            } elseif ($tx->date >= $firstDayOfYear) {
                // When the first transaction of the fiscal year is encountered,
                // save the start of year balance and value for each cryptocurrency.
                $this->cryptoInfoBag->saveStartOfYearSnapshot();
            }

            if (DateHelper::getYearFromDate($tx->date) === $this->fiscalYear) {
                // Set the daily cryptocurrencies balances until reach the current transaction date.
                $this->cryptoInfoBag->setBalancesUntilDay(DateHelper::getDayOfYear($tx->date));
            }

            if ($tx->type === Transaction::PURCHASE) {
                $this->cryptoInfoBag->incrementCryptoBalance($tx->ticker, $tx->amount, $tx);

                if (DateHelper::getYearFromDate($tx->date) === $this->fiscalYear) {
                    if ($tx->earningCategory) {
                        $this->earningsBag->addEarning($tx->exchange, $tx->earningCategory, EarningsBag::NR, $tx->value);
                    } else {
                        $this->currentYearInvestment += $tx->value;
                        $this->incrementExchangeVolume($tx->exchange, $tx->value);
                    }
                }
            } elseif ($tx->type === Transaction::SALE || $tx->type === Transaction::EXPENSE) {
                $this->cryptoInfoBag->decrementCryptoBalance($tx->ticker, $tx->amount, $tx);

                if (DateHelper::getYearFromDate($tx->date) === $this->fiscalYear) {
                    $this->earningsBag->addEarning($tx->exchange, EarningsBag::CAPITAL_GAINS, null, $tx->getCapitalGain());

                    if ($tx->type === Transaction::SALE) {
                        $this->currentYearInvestment -= $tx->value;
                    }

                    $this->currentYearIncome += $tx->value;
                    $this->currentYearPurchaseCost += $tx->getRelativePurchaseCost();

                    foreach ($tx->getRelativePurchases() AS $purchase) {
                        $purchaseTx = $purchase['transaction'];

                        if ($purchaseTx->earningCategory) {
                             // this is just the profit realized by selling a crypto earning, it doesn't include capital gain
                            $realizedProfit = $purchaseTx->value / $purchaseTx->amount * $purchase['amount'];

                            if (DateHelper::getYearFromDate($purchaseTx->date) === $this->fiscalYear) {
                                $type = EarningsBag::RAC;
                            } else {
                                $type = EarningsBag::RAP;
                            }

                            $this->earningsBag->addEarning($purchaseTx->exchange, $purchaseTx->earningCategory, $type, $realizedProfit);
                        }
                    }

                    if ($tx->type === Transaction::SALE) {
                        $this->incrementExchangeVolume($tx->exchange, $tx->value);
                    }
                }
            }
        }

        $this->cryptoInfoBag->setBalancesUntilDay(DateHelper::old_getNumerOfDaysInYear($this->fiscalYear) + 1);
        $this->cryptoInfoBag->sortByAverageValue();
    }

    /**
     * Get the first fiscal year available for the report.
     *
     * @return integer|null
     */
    public function getFirstYear() {
        $firstTransaction = $this->transactionsBag->getFirstTransaction();

        if ($firstTransaction === null) {
            return null;
        }

        return DateHelper::getYearFromDate($firstTransaction->date);
    }

    /**
     * Get the last fiscal year available for the report.
     * Actually it always return the current year, if the first fiscal year is not null.
     *
     * @return integer|null
     */
    public function getLastYear() {
        if ($this->getFirstYear() === null) {
            return null;
        }

        return DateHelper::getCurrentYear();
    }

    /**
     * Return true if the total holdings values has exceeded €51645.69 for at least
     * 7 working days during the current fiscal year, using the prices at
     * the start of the year. Art. 67, comma 1-ter TUIR.
     *
     * @return boolean
     */
    public function get51kThresholdExceeded() {
        $dailyValues = $this->cryptoInfoBag->getDailyValuesStartOfYear();
        $daysOverThreshold = 0;
        $holidays = DateHelper::getHolidays($this->fiscalYear);

        for ($i = 0; $i <= DateHelper::old_getNumerOfDaysInYear($this->fiscalYear); $i++) {
            if (DateHelper::getDayOfWeek($i, $this->fiscalYear) > 0 && !in_array($i, $holidays)) {
                if ($dailyValues[$i] > self::CAPITAL_GAINS_NO_TAX_AREA_THRESHOLD) {
                    $daysOverThreshold++;
                } else {
                    $daysOverThreshold = 0;
                }

                if ($daysOverThreshold === 7) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the exchanges trading volumes.
     *
     * @return integer[]
     */
    public function getExchangeVolumes() {
        arsort($this->exchangeVolumes);
        return $this->exchangeVolumes;
    }

    /**
     * Get the capital gains amount for the selected fiscal year.
     * Airdrop are considered as capital gains.
     *
     * @return float
     */
    public function getCapitalGains() {
        return $this->earningsBag->getCapitalGains() + $this->earningsBag->getAirdropReceived();
    }

    /**
     * Return the capital gains tax amount for the selected fiscal year.
     *
     * @return float
     */
    public function getCapitalGainsTax($allowNegative = false) {
        if (!$this->get51kThresholdExceeded() || ($this->getCapitalGains() < 0 && !$allowNegative)) {
            return 0.0;
        }

        return $this->getCapitalGains() * self::CAPITAL_GAINS_TAX_RATE;
    }

    /**
     * Filing the Modello Redditi is required if at least one section is required.
     *
     * @return boolean
     */
    public function shouldFillModelloRedditi() {
        return $this->shouldFillRW() || $this->shouldFillRT() || $this->shouldFillRM();
    }

    /**
     * Filling the RW section is required if you owned cryptocurrencies in the fiscal year.
     *
     * @return boolean
     */
    public function shouldFillRW() {
        return $this->cryptoInfoBag->getTotalValues()['average_value'] > 0.01;
    }

    /**
     * Filling the RT section is required if you have to pay taxes on capital gains.
     *
     * @return boolean
     */
    public function shouldFillRT() {
        return $this->getCapitalGainsTax() !== 0.0;
    }

    /**
     * Filling the RM section is required if you have earned interests.
     *
     * @return boolean
     */
    public function shouldFillRM() {
        return $this->earningsBag->getInterests() > 0.0;
    }

    /**
     * Get the summary of the report.
     *
     * @return array
     */
    public function getSummary() {
        return $this->recursiveFormatNumbers([
            'current_year_investment' => $this->currentYearInvestment,
            'current_year_purchase_cost' => $this->currentYearPurchaseCost,
            'current_year_income' => $this->currentYearIncome,
            'capital_gains' => $this->earningsBag->getCapitalGains(),
            'capital_gains_and_airdrop' => $this->earningsBag->getCapitalGains() + $this->earningsBag->getAirdropReceived(),
            'capital_gains_tax' => $this->getCapitalGainsTax(),
            'interests' => $this->earningsBag->getInterests(),
            'interests_tax' => $this->earningsBag->getInterests() * self::INTERESTS_EARNING_TAX_RATE,
            'total_values' => $this->cryptoInfoBag->getTotalValues()
        ]) + [
            'no_tax_area_threshold_exceeded' => $this->get51kThresholdExceeded(),
        ];
    }

    /**
     * Get the info for generate the report file.
     *
     * @return array
     */
    public function getInfoForRender() {
        return [
            'fiscal_year' => $this->fiscalYear,
            'summary' => $this->getSummary(),
            'crypto_info' => $this->cryptoInfoBag->getInfoForRender(),
            'earnings_categories' => $this->earningsBag->getCategoriesForRender(),
            'days_list' => DateHelper::getListDaysInYear($this->fiscalYear)
        ] + $this->recursiveFormatNumbers([
            'earnings' => $this->earningsBag->getInfoForRender(),
            'detailed_earnings' => $this->earningsBag->getDetailedInfoForRender(),
        ]) + $this->recursiveFormatNumbers([
            'daily_values_start_of_year' => $this->cryptoInfoBag->getDailyValuesStartOfYear(),
            'daily_values_end_of_year' => $this->cryptoInfoBag->getDailyValuesEndOfYear(),
            'daily_values_real' => $this->cryptoInfoBag->getDailyValues(),
            'exchange_volumes' => $this->getExchangeVolumes()
        ], 2, true);
    }

    public function getInfoForModelloRedditi() {
        return [
            'fiscal_year' => $this->fiscalYear,
            'sections_required' => [
                'rw' => $this->shouldFillRW(),
                'rt' => $this->shouldFillRT(),
                'rm' => $this->shouldFillRM()
            ],
            'rw' => [
                'initial_value' => (
                    $this->fiscalYear === $this->getFirstYear() ?
                    $this->transactionsBag->transactions[1]->value :
                    $this->cryptoInfoBag->getTotalValues()['value_start_of_year']
                ),
                'final_value' => $this->cryptoInfoBag->getTotalValues()['average_value']
            ]
        ];
    }

    /**
     * Set the report fiscal year and reset all the year related properties.
     *
     * @param integer $year
     */
    private function setFiscalYear($year) {
        $this->fiscalYear = intval($year);

        if ($this->fiscalYear < $this->getFirstYear() || $this->fiscalYear > $this->getLastYear()) {
            throw new InvalidYearException($year);
        }

        $this->cryptoInfoBag = new CryptoInfoBag($this->transactionsBag->transactions, $this->fiscalYear);
        $this->earningsBag = new EarningsBag();
        $this->currentYearInvestment = 0.0;
        $this->currentYearPurchaseCost = 0.0;
        $this->currentYearIncome = 0.0;
        $this->exchangeVolumes = [];
    }

    /**
     * Round and format all numbers in in array, recursively.
     *
     * @param array $array
     * @param integer $digits
     * @return array
     */
    private function recursiveFormatNumbers($array, $digits = 2, $roundOnly = false) {
        foreach ($array AS $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveFormatNumbers($value, $digits, $roundOnly);
            } else {
                $array[$key] = $roundOnly ? round($value, $digits) : number_format($value, $digits, ',', '.');
            }
        }
        return $array;
    }

    /**
     * Increment the trading volume of an exchange.
     *
     * @param string $exchange
     * @param float $value
     * @return void
     */
    private function incrementExchangeVolume($exchange, $value) {
        if (strlen($exchange) === 0) {
            return;
        } elseif (!isset($this->exchangeVolumes[$exchange])) {
            $this->exchangeVolumes[$exchange] = 0.0;
        }

        $this->exchangeVolumes[$exchange] += $value;
    }
}
