<?php

namespace CrypTax\Models;

class EarningsBag
{
    const CAPITAL_GAINS = 'capital_gains';
    const RAP = 'rap';
    const RAC = 'rac';
    const NR = 'nr';

    const TYPES = [self::RAP, self::RAC, self::NR];

    /**
     * Earnings array.
     *
     * @var array
     */
    private $earnings;

    /**
     * Initialize the earnings array.
     */
    public function __construct() {
        $this->earnings = [
            'capital_gains' => 0.0
        ];
    }

    /**
     * Add an earning amount.
     *
     * @param string $exchange
     * @param string $category
     * @param string $type
     * @param float $value
     */
    public function addEarning($exchange, $category, $type, $value) {
        if ($category === self::CAPITAL_GAINS) {
            $this->earnings['capital_gains'] += $value;
            return;
        }

        if ($type === self::RAC) {
            // if the type is RAC (realizzato anno corrente), then we have to decrement the NR (non realizzato) total value
            $this->addEarning($exchange, $category, self::NR, - $value);
        }

        if (!isset($this->earnings[$category][$exchange][$type])) {
            $this->earnings[$category][$exchange][$type] = 0.0;
        }

        $this->earnings[$category][$exchange][$type] += $value;
    }

    /**
     * Get the selected fiscal year capital gains.
     *
     * @return float
     */
    public function getCapitalGains() {
        return $this->earnings[self::CAPITAL_GAINS] ?? 0.0;
    }

    /**
     * Get the selected fiscal year interests received value.
     *
     * @return float
     */
    public function getInterests() {
        return $this->getCategoryTotalValue(Transaction::INTEREST);
    }

    /**
     * Get the selected fiscal year airdrop received value.
     *
     * @return float
     */
    public function getAirdropReceived() {
        return $this->getCategoryTotalValue(Transaction::AIRDROP);
    }

    /**
     * Get the categories list with the relatives Italian names.
     *
     * @return array
     */
    public function getCategoriesForRender() {
        $categories = [];

        foreach (array_keys($this->earnings) AS $category) {
            if ($category === self::CAPITAL_GAINS) {
                continue;
            }

            $key = $category;

            if (isset(Transaction::EARNING_CATEGORIES_IT[$category])) {
                $category = Transaction::EARNING_CATEGORIES_IT[$category];
            }

            $categories[$key] = $category;
        }

        asort($categories);

        return $categories;
    }

    /**
     * Get the total value of a given category.
     *
     * @param string $category
     * @return float
     */
    private function getCategoryTotalValue($category) {
        $totalValue = 0.0;

        if (!isset($this->earnings[$category])) {
            return $totalValue;
        }

        foreach ($this->earnings[$category] AS $exchanges => $types) {
            foreach ($types AS $type => $value) {
                $totalValue += $value;
            }
        }

        return $totalValue;
    }

    /**
     * Get the info used for the report rendering.
     *
     * @return array
     */
    public function getInfoForRender() {
        $earnings = [
            'capital_gains' => $this->earnings['capital_gains']
        ];

        foreach ($this->earnings AS $category => $exchanges) {
            if ($category === self::CAPITAL_GAINS) continue;

            foreach (self::TYPES AS $type) {
                if (!isset($earnings[$type][$category])) {
                    $earnings[$type][$category] = 0.0;
                }
            }

            foreach ($exchanges AS $types) {
                foreach ($types AS $type => $value) {
                    $earnings[$type][$category] += $value;
                }
            }
        }

        return $earnings;
    }

    /**
     * Get the detailed info used for the report rendering.
     *
     * @return array
     */
    public function getDetailedInfoForRender() {
        $detailedEarnings = $this->earnings;

        unset($detailedEarnings[self::CAPITAL_GAINS]);

        foreach ($detailedEarnings AS $category => &$exchanges) {
            foreach ($exchanges AS $exchange => $types) {
                foreach (self::TYPES AS $type) {
                    if (!isset($detailedEarnings[$category][$exchange][$type])) {
                        $detailedEarnings[$category][$exchange][$type] = 0.0;
                    }
                }
            }
            ksort($exchanges);
        }

        ksort($detailedEarnings);

        return $detailedEarnings;
    }
}
