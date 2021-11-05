<?php

namespace CrypTax\Helpers;

class CryptoInfoHelper
{
    /**
     * API endpoint host.
     *
     * @var string
     */
    private const API_HOST = 'https://cryptohistory.one/api';

    private static $prices = [];

    public static function getCryptoName($ticker) {
        return self::getCryptoData($ticker, DateHelper::getToday())['name'];
    }

    public static function getCryptoTicker($ticker) {
        return self::getCryptoData($ticker, DateHelper::getToday())['ticker'];
    }

    public static function getCryptoPrice($ticker, $date) {
        //var_dump(debug_backtrace()[1]['function']);
        return self::getCryptoData($ticker, $date)['price'];
    }

    public static function getCryptoPriceStartOfYear($ticker, $year) {
        return self::getCryptoPrice($ticker, $year . '-01-01');
    }

    public static function getCryptoPriceEndOfYear($ticker, $year) {
        return self::getCryptoPrice($ticker, $year . '-12-31');
    }

    private static function getCryptoData($ticker, $date) {
        //var_dump([$ticker, $date]);
        $date = DateHelper::getDateFromString($date);

        if (isset(CUSTOM_TICKERS[$ticker])) {
            $ticker = CUSTOM_TICKERS[$ticker];
        }

        if ($date > DateHelper::getDateFromString('-3 days')) {
            return self::getCryptoData($ticker, DateHelper::getDateFromString('-3 days'));
        }

        $ticker = strtoupper($ticker);

        if (!isset(self::$prices[$ticker][$date])) {
            self::$prices[$ticker][$date] = [
                'name' => $ticker,
                'ticker' => $ticker,
                'price' => 0.0,
                'found' => false,
                'fetched' => false
            ];

            self::fetchCryptoData($ticker, $date);
        }

        self::$prices[$ticker][$date]['reqired'] = true;

        return self::$prices[$ticker][$date];
    }

    private static function fetchCryptoData($ticker, $date) {
        self::fetchCryptoDataFromDb($ticker, $date);
        self::fetchCryptoDataFromApi($ticker, $date);
    }

    private static function getDateRangeToFetch($ticker, $date, $rangeDays = 366) {
        $firstDate = DateHelper::getDateFromString($date . ' - ' . (intval($rangeDays / 2)) . ' days');
        $lastDate = DateHelper::getDateFromString($date . ' + ' . (intval($rangeDays / 2)) . ' days');

        if ($firstDate > DateHelper::getDateFromString('-3 days')) {
            $firstDate = DateHelper::getDateFromString('-3 days');
        }

        if ($lastDate > DateHelper::getDateFromString('-3 days')) {
            $lastDate = DateHelper::getDateFromString('-3 days');
        }

        while ((self::$prices[$ticker][$firstDate]['fetched'] ?? false) && $firstDate <= $lastDate) {
            $firstDate = DateHelper::getDateFromString($firstDate . ' + 1 day');
        }

        while ((self::$prices[$ticker][$lastDate]['fetched'] ?? false) && $firstDate <= $lastDate) {
            $lastDate = DateHelper::getDateFromString($lastDate . ' - 1 day');
        }

        return [$firstDate, $lastDate];
    }

    private static function fetchCryptoDataFromDb($ticker, $date) {
        [$firstDate, $lastDate] = self::getDateRangeToFetch($ticker, $date);

        if ($firstDate > $lastDate) {
            // if firstDate and lastDate overlap, all the required data have already been fetched
            return;
        }

        $currentTime = time();
        $stmt = DbHelper::getConnection()->prepare('SELECT date, quote, ticker, name, found FROM cache WHERE ticker = ? AND date >= ? AND date <= ? AND (expiration = 0 OR expiration > ?)');
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

                $currDate = DateHelper::getDateFromString($currDate . ' + 1 day');
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
                $expiration = time() + $value['cache_max_age'];
                $stmt = DbHelper::getConnection()->prepare('INSERT INTO cache (ticker, name, date, quote, expiration, found) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quote = ?, expiration = ?, found = ?');
                $stmt->bind_param('sssdiidii', $value['ticker'], $value['name'], $value['date'], $price, $expiration, $found, $price, $expiration, $found);
                $stmt->execute();
                $stmt->close();
            }
        }

        self::fetchCryptoDataFromDb($ticker, $date);
    }
}
