<?php

namespace CrypTax\Utils;

class CryptoInfoUtils
{
    /**
     * API endpoint host.
     *
     * @var string
     */
    private const API_HOST = 'https://cryptohistory.one/api';

    /**
     * List of stablecoins pegged to the euro.
     *
     * @var string[]
     */
    private const EUR_STABLECOINS = ['EURx'];

    /**
     * Array cache with all requested cryptocurrency prices.
     *
     * @var array
     */
    private static $prices = [];

    /**
     * Set to true if trying to get too early cryptocurrency prices.
     *
     * @var boolean
     */
    private static $requestedTooEarlyPrices = false;

    /**
     * Set to true if trying to get too late cryptocurrency prices.
     *
     * @var boolean
     */
    private static $requestedTooLatePrices = false;

    /**
     * Get the crypto name by the ticker.
     *
     * @param string $ticker
     * @return string
     */
    public static function getCryptoName($ticker) {
        return self::getCryptoData($ticker, DateUtils::getToday())['name'];
    }

    /**
     * Get the crypto real ticker by the user input ticker.
     * This mainly fixes the letters capitalization.
     *
     * @param string $ticker
     * @return string
     */
    public static function getCryptoTicker($ticker) {
        return self::getCryptoData($ticker, DateUtils::getToday())['ticker'];
    }

    /**
     * Get a cryptocurrency price.
     *
     * @param string $ticker
     * @param string $date
     * @return float
     */
    public static function getCryptoPrice($ticker, $date) {
        return self::getCryptoData($ticker, $date)['price'];
    }

    /**
     * Get a cryptocurrency price at the beginning of the year.
     *
     * @param string $ticker
     * @param integer $year
     * @return float
     */
    public static function getCryptoPriceStartOfYear($ticker, $year) {
        return self::getCryptoPrice($ticker, $year . '-01-01');
    }

    /**
     * Get a cryptocurrency price at the end of the year.
     *
     * @param string $ticker
     * @param integer $year
     * @return float
     */
    public static function getCryptoPriceEndOfYear($ticker, $year) {
        return self::getCryptoPrice($ticker, $year . '-12-31');
    }

    public static function getWarnings($year = null) {
        $warnings = [
            'too_early' => ($year === null || $year < 2009) ? self::$requestedTooEarlyPrices : false,
            'too_early_date' => DateUtils::getDateFromString('2009-01-03'),
            'too_late' => ($year === null || $year === DateUtils::getCurrentYear()) ? self::$requestedTooLatePrices : false,
            'too_late_date' => DateUtils::getDateFromString('-3 days'),
            'prices' => self::getNotFoundPrices($year)
        ];

        $warnings['show'] = $warnings['too_early'] || $warnings['too_late'] || count($warnings['prices']) > 0;

        return $warnings;
    }

    private static function getNotFoundPrices($year = null) {
        $notFoundPrices = [];

        foreach (self::$prices AS $ticker => $dates) {
            $firstDate = null;
            $lastDate = null;

            ksort($dates);
            foreach ($dates AS $date => $value) {
                if ($year !== null && $year !== intval(date('Y', strtotime($date)))) {
                    continue;
                }

                if (!$value['found']) {
                    if ($value['required']) {
                        if ($firstDate === null) {
                            $firstDate = $date;
                            $lastDate = $date;
                        } else {
                            $lastDate = $date;
                        }
                    }
                } else {
                    if ($firstDate !== null) {
                        $notFoundPrices[] = ['ticker' => $ticker, 'from' => $firstDate, 'to' => $lastDate];
                    }
                    $firstDate = null;
                    $lastDate = null;
                }
            }

            if ($firstDate !== null) {
                $notFoundPrices[] = ['ticker' => $ticker, 'from' => $firstDate, 'to' => $lastDate];
            }
            $firstDate = null;
            $lastDate = null;
        }

        return $notFoundPrices;
    }

    /**
     * Get a cryptocurrency info and price on a specific date.
     *
     * @param string $ticker
     * @param string $date
     * @return array
     */
    private static function getCryptoData($ticker, $date) {
        $date = DateUtils::getDateFromString($date);

        if (isset(CUSTOM_TICKERS[$ticker])) {
            $ticker = CUSTOM_TICKERS[$ticker];
        }

        if (self::isEurStablecoin($ticker)) {
            return [
                'name' => self::getEurStablecoinRealTicker($ticker) . ' stablecoin',
                'ticker' => self::getEurStablecoinRealTicker($ticker),
                'price' => 1.0,
                'required' => true,
                'found' => true,
                'fetched' => true
            ];
        }

        // prices before the creation of Bitcoin do not exist
        if ($date < DateUtils::getDateFromString('2009-01-03')) {
            self::$requestedTooEarlyPrices = true;
            return self::getCryptoData($ticker, DateUtils::getDateFromString('2009-01-03'));
        }

        // prices of the last 3 days may not yet be available
        if ($date > DateUtils::getDateFromString('-3 days')) {
            self::$requestedTooLatePrices = true;
            return self::getCryptoData($ticker, DateUtils::getDateFromString('-3 days'));
        }

        $ticker = strtoupper($ticker);

        if (!isset(self::$prices[$ticker][$date])) {
            self::$prices[$ticker][$date] = [
                'name' => $ticker,
                'ticker' => $ticker,
                'price' => 0.0,
                'required' => true,
                'found' => false,
                'fetched' => false
            ];

            self::fetchCryptoData($ticker, $date);
        }

        self::$prices[$ticker][$date]['required'] = true;

        return self::$prices[$ticker][$date];
    }

    /**
     * Fetch cryptocurrency data from database cache and API.
     *
     * @param string $ticker
     * @param string $date
     * @return void
     */
    private static function fetchCryptoData($ticker, $date) {
        self::fetchCryptoDataFromDb($ticker, $date);
        self::fetchCryptoDataFromApi($ticker, $date);
    }

    /**
     * To reduce number of DB/API calls, each time a predefined length range of dates is requested.
     * This method calculates the first and the last date of the range.
     *
     * @param string $ticker
     * @param string $date
     * @param integer $rangeDays
     * @return array
     */
    private static function getDateRangeToFetch($ticker, $date, $rangeDays = 366) {
        $firstDate = DateUtils::getDateFromString($date . ' - ' . (intval($rangeDays / 2)) . ' days');
        $lastDate = DateUtils::getDateFromString($date . ' + ' . (intval($rangeDays / 2)) . ' days');

        if ($firstDate > DateUtils::getDateFromString('-3 days')) {
            $firstDate = DateUtils::getDateFromString('-3 days');
        }

        if ($lastDate > DateUtils::getDateFromString('-3 days')) {
            $lastDate = DateUtils::getDateFromString('-3 days');
        }

        while ((self::$prices[$ticker][$firstDate]['fetched'] ?? false) && $firstDate <= $lastDate) {
            $firstDate = DateUtils::getDateFromString($firstDate . ' + 1 day');
        }

        while ((self::$prices[$ticker][$lastDate]['fetched'] ?? false) && $firstDate <= $lastDate) {
            $lastDate = DateUtils::getDateFromString($lastDate . ' - 1 day');
        }

        return [$firstDate, $lastDate];
    }

    /**
     * Fetch cryptocurrency data from database cache.
     *
     * @param string $ticker
     * @param string $date
     * @return void
     */
    private static function fetchCryptoDataFromDb($ticker, $date) {
        [$firstDate, $lastDate] = self::getDateRangeToFetch($ticker, $date);

        if ($firstDate > $lastDate) {
            // if firstDate and lastDate overlap, all the required data have already been fetched
            return;
        }

        $currentTime = defined('FAKE_CACHE_EXPIRATION') ? (time() + (60 * 60)) : time();
        $stmt = DbUtils::getConnection()->prepare('SELECT date, quote, ticker, name, found FROM cache WHERE ticker = ? AND date >= ? AND date <= ? AND (expiration = 0 OR expiration > ?)');
        $stmt->bind_param('sssi', $ticker, $firstDate, $lastDate, $currentTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        while ($resultArray = $result->fetch_assoc()) {
            $resultDate = $resultArray['date'];

            self::$prices[$ticker][$resultDate] = [
                'name' => $resultArray['name'],
                'ticker' => $resultArray['ticker'],
                'price' => $resultArray['quote'],
                'required' => self::$prices[$ticker][$resultDate]['required'] ?? false,
                'fetched' => true,
                'found' => $resultArray['found'] ? true : false
            ];
        }
    }

    /**
     * Fetch cryptocurrency data from cryptohistory.one API.
     *
     * @param string $ticker
     * @param string $date
     * @return void
     */
    private static function fetchCryptoDataFromApi($ticker, $date) {
        [$firstDate, $lastDate] = self::getDateRangeToFetch($ticker, $date);

        if ($firstDate > $lastDate) {
            // if firstDate and lastDate overlap, all the required data have already been fetched
            return;
        }

        $values = json_decode(@file_get_contents(self::API_HOST . '/' . $ticker . '/' . $firstDate . '/' . $lastDate), true);

        if ($values === null) {
            // The request to the API did not produce any result
            // (the cryptocurrency was not found, or a communication error was encountered).
            // Consider the price = 0 and cache it for 24 hours.
            $values = [];
            $currDate = $firstDate;
            $price = 0.0;

            while ($currDate <= $lastDate) {
                $values[] = [
                    'date' => $currDate,
                    'name' => $ticker,
                    'ticker' => $ticker,
                    'price_eur' => 0.0,
                    'cache_max_age' => 60 * 60 * 24,
                    'found' => false
                ];

                $currDate = DateUtils::getDateFromString($currDate . ' + 1 day');
            }
        }

        if (is_iterable($values)) {
            if (isset($values['ticker'])) {
                // a single day price was returned; put it in an array to make the next foreach work
                $values = [$values];
            }

            foreach ($values AS $value) {
                $price = floatVal($value['price_eur']);
                $found = $value['found'] ? 1 : 0;
                $expiration = ($value['found'] ? 0 : time() + min($value['cache_max_age'], 60 * 60 * 24 * 30));
                $stmt = DbUtils::getConnection()->prepare('INSERT INTO cache (ticker, name, date, quote, expiration, found) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quote = ?, expiration = ?, found = ?');
                $stmt->bind_param('sssdiidii', $value['ticker'], $value['name'], $value['date'], $price, $expiration, $found, $price, $expiration, $found);
                $stmt->execute();
                $stmt->close();
            }
        }

        self::fetchCryptoDataFromDb($ticker, $date);
    }

    private static function isEurStablecoin(string $ticker): bool {
        return self::getEurStablecoinRealTicker($ticker) !== null;
    }

    private static function getEurStablecoinRealTicker(string $ticker): ?string {
        foreach (self::EUR_STABLECOINS AS $stablecoin) {
            if (strtolower($stablecoin) === strtolower($ticker)) {
                return $stablecoin;
            }
        }

        return null;
    }
}
