<?php

function getCryptoData($ticker, $date) {
    global $db, $GLOBAL_PRICES;

    if (isset(CUSTOM_TICKERS[$ticker])) {
        $ticker = CUSTOM_TICKERS[$ticker];
    }

    if ($date > date('Y-m-d', strtotime('-2 days'))) {
        return(getCryptoData($ticker, date('Y-m-d', strtotime('-2 days'))));
    }

    $ticker = strtoupper($ticker);

    if (isset($GLOBAL_PRICES[$ticker][$date])) {
        return $GLOBAL_PRICES[$ticker][$date];
    }

    $GLOBAL_PRICES[$ticker][$date] = null;

    return [
        'name' => $ticker,
        'ticker' => $ticker,
        'price' => 0.0
    ];
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

// This function is a total mess, but it seems working, so for the moment I don't touch it :)
// (Just kidding, I absolutely have to rewrite it)
function fetchGlobalCryptoData() {
    global $db, $GLOBAL_PRICES;

    foreach ($GLOBAL_PRICES AS $ticker => $dates) {
        for ($j = 0; $j < 2; $j++) {
            ksort($dates);

            $groups = [];

            $startDate = null;
            $prevDate = null;
            foreach (array_keys($dates) AS $date) {
                if (!array_key_exists($date, $GLOBAL_PRICES[$ticker])) {
                    echo "err $j $ticker $date \n";
                }
                if ($GLOBAL_PRICES[$ticker][$date] !== null) {
                    continue;
                }

                for ($i = 0; $i < 2; $i++) {
                    if ($startDate === null) {
                        $startDate = $date;
                    }

                    if ($i === 0) {
                        if ($date > date('Y-m-d', strtotime($startDate . ' + 365 days'))) {
                            $groups[] = ['start' => $startDate, 'end' => $prevDate ?? $startDate];
                            $startDate = null;
                            $prevDate = null;
                        }
                    }
                }

                if ($prevDate === null || (strtotime($date) - strtotime($prevDate)) < (60 * 60 * 24 * 30)) {
                    $prevDate = $date;
                    continue;
                }

                $groups[] = ['start' => $startDate, 'end' => $prevDate ?? $startDate];
                $startDate = $date;
                $prevDate = null;
            }

            if ($startDate && $prevDate) {
                $groups[] = ['start' => $startDate, 'end' => $prevDate];
            }

            foreach ($groups AS $group) {
                if ($j === 0) {
                    $now = time();
                    $stmt = $db->prepare('SELECT date, quote, ticker, name FROM cache WHERE ticker = ? AND date >= ? AND date <= ? AND (expiration = 0 OR expiration > ?)');
                    $stmt->bind_param('sssi', $ticker, $group['start'], $group['end'], $now);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stmt->close();
                    $db->next_result();

                    while ($resultArray = $result->fetch_assoc()) {
                        if (array_key_exists($resultArray['date'], $GLOBAL_PRICES[$ticker])) {
                            $name = $resultArray['name'] ?? '';
                            $newTicker = $resultArray['ticker'] ?? $ticker;
                            $price = floatVal($resultArray['quote'] ?? 0);

                            $GLOBAL_PRICES[$ticker][$resultArray['date']] = [
                                'name' => $name,
                                'ticker' => $newTicker,
                                'price' => $price
                            ];
                        }
                    }
                } elseif ($j === 1) {
                    $start = microtime(true);
                    //echo '<!-- (REQ) ' . $ticker . ', ' . $group['start'] . '-' . $group['end'] . ' -->' . PHP_EOL;
                    $values = json_decode(@file_get_contents('http://cryptohistory.one/api/' . $ticker . '/' . $group['start'] . '/' . $group['end']), true);
                    $end = microtime(true);
                    //echo '<!-- (RES) ' . $ticker . ', ' . $group['start'] . '-' . $group['end'] . ': ' . round($end - $start, 4) . ' -->' . PHP_EOL;

                    $realValues = $values;

                    if ($values === null) {
                        $currDate = $group['start'];
                        $name = $ticker;
                        $price = 0;
                        while ($currDate <= $group['end']) {
                            $expiration = time() + (60 * 60 * 24 * 7);
                            $stmt2 = $db->prepare('INSERT INTO cache (ticker, name, date, quote, expiration) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quote = ?, expiration = ?');
                            $stmt2->bind_param('sssdidi', $ticker, $name, $currDate, $price, $expiration, $price, $expiration);
                            $stmt2->execute();
                            $stmt2->close();

                            $currDate = date('Y-m-d', strtotime($currDate . ' + 1 day'));
                        }
                    }

                    if (is_iterable($values)) {
                        if (isset($values['ticker'])) {
                            $realValues = [$values];
                        }

                        foreach ($realValues AS $value) {
                            if (array_key_exists($value['date'], $GLOBAL_PRICES[$ticker])) {
                                $name = $value['name'] ?? '';
                                $newTicker = $value['ticker'] ?? $ticker;
                                $price = floatVal($value['price_eur'] ?? 0);

                                $GLOBAL_PRICES[$ticker][$value['date']] = [
                                    'name' => $name,
                                    'ticker' => $newTicker,
                                    'price' => $price
                                ];

                                $expiration = time() + $value['cache_max_age'];
                                $stmt2 = $db->prepare('INSERT INTO cache (ticker, name, date, quote, expiration) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quote = ?, expiration = ?');
                                $stmt2->bind_param('sssdidi', $ticker, $name, $value['date'], $price, $expiration, $price, $expiration);
                                $stmt2->execute();
                                $stmt2->close();
                            }
                        }
                    }
                }
            }
        }

    }
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
