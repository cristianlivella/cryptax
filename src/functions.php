<?php

function getCryptoData($ticker, $date) {
    global $db, $GLOBAL_PRICES;

    $date = date('Y-m-d', strtotime($date));

    if (isset(CUSTOM_TICKERS[$ticker])) {
        $ticker = CUSTOM_TICKERS[$ticker];
    }

    if ($date > date('Y-m-d', strtotime('-3 days'))) {
        return(getCryptoData($ticker, date('Y-m-d', strtotime('-3 days'))));
    }

    $ticker = strtoupper($ticker);

    if (!isset($GLOBAL_PRICES[$ticker][$date])) {
        $GLOBAL_PRICES[$ticker][$date] = [
            'name' => $ticker,
            'ticker' => $ticker,
            'price' => 0.0,
            'found' => false,
            'fetched' => false
        ];

        fetchCryptoData($ticker, $date);
    }

    $GLOBAL_PRICES[$ticker][$date]['required'] = true;

    return $GLOBAL_PRICES[$ticker][$date];
}

function getCryptoName($ticker) {
    return getCryptoData($ticker, date('Y-m-d'))['name'];
}

function getCryptoTicker($ticker) {
    return getCryptoData($ticker, date('Y-m-d'))['ticker'];
}

function getCryptoPrice($ticker, $date) {
    return getCryptoData($ticker, date('Y-m-d', strtotime($date)))['price'];
}

function getCryptoKey($ticker) {
    global $cryptoInfo;

    if (isset(CUSTOM_TICKERS[$ticker])) {
        $ticker = CUSTOM_TICKERS[$ticker];
    }

    foreach ($cryptoInfo AS $id => $crypto) {
        if (strtolower($crypto['symbol']) === strtolower($ticker)) {
            return $id;
        }
    }
}

function getDateRangeToFetch($ticker, $date, $rangeDays = 366) {
    global $GLOBAL_PRICES;

    $firstDate = date('Y-m-d', strtotime($date . ' - ' . (intval($rangeDays / 2)) . ' days'));
    $lastDate = date('Y-m-d', strtotime($date . ' + ' . (intval($rangeDays / 2)) . ' days'));

    while (($GLOBAL_PRICES[$ticker][$firstDate]['fetched'] ?? false) === true && $firstDate <= $lastDate) {
        $firstDate = date('Y-m-d', strtotime($firstDate . ' + 1 day'));
    }

    while (($GLOBAL_PRICES[$ticker][$lastDate]['fetched'] ?? false) === true && $firstDate <= $lastDate) {
        $lastDate = date('Y-m-d', strtotime($lastDate . ' - 1 day'));
    }

    return [$firstDate, $lastDate];
}

function fetchCryptoDataFromDb($ticker, $date) {
    global $GLOBAL_PRICES, $db;

    [$firstDate, $lastDate] = getDateRangeToFetch($ticker, $date);

    if ($firstDate > $lastDate) {
        // if firstDate and lastDate overlap, all the required data have already been fetched
        return;
    }

    // fetch prices from db cache
    $now = time();
    $stmt = $db->prepare('SELECT date, quote, ticker, name, found FROM cache WHERE ticker = ? AND date >= ? AND date <= ? AND (expiration = 0 OR expiration > ?)');
    $stmt->bind_param('sssi', $ticker, $firstDate, $lastDate, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    while ($resultArray = $result->fetch_assoc()) {
        $resultDate = $resultArray['date'];

        $GLOBAL_PRICES[$ticker][$resultDate] = [
            'name' => $resultArray['name'],
            'ticker' => $resultArray['ticker'],
            'price' => $resultArray['quote'],
            'required' => $GLOBAL_PRICES[$ticker][$resultDate]['required'] ?? false,
            'fetched' => true,
            'found' => $resultArray['found'] ? true : false
        ];
    }
}

function fetchCryptoDataFromApi($ticker, $date) {
    global $GLOBAL_PRICES, $db;

    [$firstDate, $lastDate] = getDateRangeToFetch($ticker, $date);

    if ($firstDate > $lastDate) {
        // if firstDate and lastDate overlap, all the required data have already been fetched
        return;
    }

    // fetch prices from cryptohistory.one API
    $start = microtime(true);
    echo '<!-- (REQ) ' . $ticker . ', ' . $firstDate . '-' . $lastDate . ' -->' . PHP_EOL;
    $values = json_decode(@file_get_contents('http://cryptohistory.one/api/' . $ticker . '/' . $firstDate . '/' . $lastDate), true);
    $end = microtime(true);
    echo '<!-- (RES) ' . $ticker . ', ' . $firstDate . '-' . $lastDate . ': ' . round($end - $start, 4) . ' -->' . PHP_EOL;

    if ($values === null) {
        // The request to the API did not produce any result (the cryptocurrency was not found, or a communication error was encountered).
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
            $currDate = date('Y-m-d', strtotime($currDate . ' + 1 day'));
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
            $stmt = $db->prepare('INSERT INTO cache (ticker, name, date, quote, expiration, found) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quote = ?, expiration = ?, found = ?');
            $stmt->bind_param('sssdiidii', $value['ticker'], $value['name'], $value['date'], $price, $expiration, $found, $price, $expiration, $found);
            $stmt->execute();
            $stmt->close();
        }
    }

    // re fetch the data from the db
    fetchCryptoDataFromDb($ticker, $date);
}

function fetchCryptoData($ticker, $date) {
    fetchCryptoDataFromDb($ticker, $date);
    fetchCryptoDataFromApi($ticker, $date);
}

function incrementExchangeVolume($exchange, $value) {
    global $exchangeVolumes;

    if (strlen($exchange) === 0) {
        return;
    } elseif (!isset($exchangeVolumes[$exchange])) {
        $exchangeVolumes[$exchange] = 0.0;
    }

    $exchangeVolumes[$exchange] += $value;
}

function formatBalance($balance, $price) {
    $digits = 2;

    if ($price > 10 && $balance > 0) {
        $digits = 8;
    }

    return number_format($balance, $digits, ',', '.');
}
