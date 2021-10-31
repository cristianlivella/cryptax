<?php

require 'config.php';
require 'functions.php';

// database connection
$db = mysqli_connect(DB_HOST, DB_USER, DB_PSW, DB_NAME);
mb_internal_encoding("UTF-8");
$db->query('SET NAMES utf8');

if (!$db) {
    die('Error! Cannot connect to db');
}

$tableExists = $db->query('SELECT 1 FROM cache LIMIT 1');
if (!$tableExists) {
    $db->multi_query(file_get_contents('dump.sql'));
    while ($db->next_result());
}

// disable time limit; fetching crypto prices could require some time
set_time_limit(0);

// this array will contain the prices of cryptocurrencies
$GLOBAL_PRICES = [];

$fiscalYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

for ($i = 0; $i < 2; $i++) {
    // The main part of the script is executed twice.
    //
    // The first time we insert in the $GLOBAL_PRICES the date and the currency
    // for which we need prices, then the prices are retrieved from the API or the database
    // and on the second iteration they are used.
    //
    // This may sound stupid, but it was an easy way to optimize the script so that
    // we don't have to make a call to the API or database for each day requested,
    // but to group them together to make the script execution faster.

    $cryptoInfo = [];           // info and calculations results
    $transactions = [];         // transaction array

    $earnings = [
        'plusvalenze' => 0.0,   // capital gain
        'rap' => [],            // realizzati anno precedente
        'rac' => [],            // realizzati anno corrente
        'nr' => []              // non realizzati
    ];

    // this values refer to $fiscalYear
    $totalInvestment = 0.0;
    $totalTakings = 0.0;
    $totalCosts = 0.0;          // this is tricky, is not the real cost of the current year :)

    $dailyTotalValues = [];
    $exchangeVolumes = [];

    // START TRANSACTIONS FILE PARSING
    // Undocumented feature: the transactions.csv file can be composed of the concatenation
    // of several files with different id sequences. To avoid problems with multiple ids,
    // a number is added at the begin of each id, which is incremented each time the numbering starts again.
    $section = 0;
    $lastId = 0;

    $rawTransactions = explode(PHP_EOL, file_get_contents(TRANSACTIONS_FILE));
    unset($rawTransactions[count($rawTransactions) - 1]);

    foreach ($rawTransactions AS $tx) {
        $tx = explode(';', $tx);

        if (!is_numeric($tx[0])) {
            continue;
        } elseif ($lastId > $tx[0]) {
            $section++;
        } elseif ($section > 25) {
            die('Error! Max supported numerations: 26');
        }

        $ticker = $tx[5];

        $transactions[] = [
            'id' => chr(65 + $section) . $tx[0],
            'date' => str_replace('/', '-', $tx[1]),
            'type' => $tx[2],
            'amount' => floatval($tx[4]),
            'value' => floatval($tx[3]),
            'crypto' => $tx[5],
            'plusvalenza' => floatval(str_replace(' ', '', $tx[8])),
            'spesa' => floatval($tx[4]),
            'tx_acquisto' => chr(65 + $section) . $tx[9],
            'categoria' => $tx[11],
            'exchange' => $tx[10]
        ];

        $lastId = $tx[0];

        if (!isset($cryptoInfo[$ticker])) {
            $name = getCryptoName($ticker);
            $symbol = getCryptoTicker($ticker);

            $valueStartOfYear = getCryptoPrice($ticker, $fiscalYear . '-01-01');
            $valueEndOfYear = getCryptoPrice($ticker, $fiscalYear . '-12-31');

            $cryptoInfo[$ticker] = [
                'symbol' => $symbol,
                'name' => $name,
                'value_start' => $valueStartOfYear,
                'value_end' => $valueEndOfYear,
                'balance_start' => 0.0,
                'balance' => 0.0,
                'sum_balance' => 0.000000,
                'start_of_year_balance_eur' => -1.000000,
                'end_of_year_balance_eur' => 0.000000,
                'average_balance' => 0,
                'average_balance_eur' => 0,
                'max_balance' => 0
            ];
        }
    }

    // SORT THE TRANSACTIONS LIST BY DATE
    usort($transactions, function ($a, $b) {
        if ($a['date'] === $b['date']) {
            return $a['id'] > $b['id'];
        }
        return strtotime($a['date']) > strtotime($b['date']);
    });

    $firstDayOfYear = mktime(0, 0, 0, 1, 1, $fiscalYear);
    $lastDayOfYear = mktime(0, 0, 0, 12, 31, $fiscalYear);
    $currentDayOfYear = 0;
    $startOfYearDataInitialized = false;

    foreach ($transactions AS $tx) {
        if (strtotime($tx['date']) > $lastDayOfYear) {
            // don't elaborate transactions made after the end of the current selected year
            break;
        } elseif (strtotime($tx['date']) >= $firstDayOfYear && !$startOfYearDataInitialized) {
            // save the start of year balance and value for each cryptocurrency
            foreach (array_keys($cryptoInfo) AS $ticker) {
                $cryptoInfo[$ticker]['balance_start'] = $cryptoInfo[$ticker]['balance'];
                $cryptoInfo[$ticker]['start_of_year_balance_eur'] = $cryptoInfo[$ticker]['balance'] * $cryptoInfo[$ticker]['value_start'];
            }
            $startOfYearDataInitialized = true;
        }

        while ($currentDayOfYear < date('z', strtotime($tx['date'])) && intVal(date('Y', strtotime($tx['date']))) === $fiscalYear) {
            // Increment the current day until it reach the date of the current transaction.
            // In the meantime sum the balance in order to calculate the average balance later.
            foreach (array_keys($cryptoInfo) AS $ticker) {
                if (!isset($dailyTotalValues[$ticker])) {
                    $dailyTotalValues[$ticker] = [];
                }
                $dailyTotalValues[$ticker][$currentDayOfYear] = $cryptoInfo[$ticker]['balance'];
                $cryptoInfo[$ticker]['sum_balance'] += $cryptoInfo[$ticker]['balance'];
                if ($cryptoInfo[$ticker]['balance'] > $cryptoInfo[$ticker]['max_balance']) {
                    $cryptoInfo[$ticker]['max_balance'] = $cryptoInfo[$ticker]['balance'];
                }
            }
            $currentDayOfYear++;
        }

        // Here starts the real tricky magic logic :)
        if (in_array($tx['type'], ['acquisto', 'purchase'])) {
            // transaction type = acquisto
            $cryptoInfo[$tx['crypto']]['balance'] += $tx['amount'];
            $exchange = $tx['exchange'];

            if ($tx['value'] === 0.0) {
                // if the value of the purchase is 0, then it's an earn
                if (!isset($earnings['nr'][$tx['categoria']])) {
                    // initialize the earnings values for the current category
                    $earnings['rap'][$tx['categoria']] = 0.0;
                    $earnings['rac'][$tx['categoria']] = 0.0;
                    $earnings['nr'][$tx['categoria']] = 0.0;

                    // _d means detailed; here we don't save the total value but the earns from each exchange/service
                    $earnings['rap_d'][$tx['categoria']] = [];
                    $earnings['rac_d'][$tx['categoria']] = [];
                    $earnings['nr_d'][$tx['categoria']] = [];
                }

                if (!isset($earnings['nr_d'][$tx['categoria']][$exchange])) {
                    // initialize the detailed earnings vars
                    $earnings['rap_d'][$tx['categoria']][$exchange] = 0.0;
                    $earnings['rac_d'][$tx['categoria']][$exchange] = 0.0;
                    $earnings['nr_d'][$tx['categoria']][$exchange] = 0.0;
                }
            }

            if (intval(date('Y', strtotime($tx['date']))) === $fiscalYear) {
                $totalInvestment += $tx['value'];

                // if the transaction was made in the $fiscalYear, increment the used exchange volume
                incrementExchangeVolume($exchange, $tx['value']);

                if ($tx['value'] === 0.0) {
                    // if the transaction is an earn, sum the "non realizzati" values
                    $earnings['nr'][$tx['categoria']] += $tx['amount'] * getCryptoPrice($tx['crypto'], date('Y-m-d', strtotime($tx['date'])));
                    $earnings['nr_d'][$tx['categoria']][$exchange] += $tx['amount'] * getCryptoPrice($tx['crypto'], date('Y-m-d', strtotime($tx['date'])));
                }
            }
        } elseif (in_array($tx['type'], ['vendita', 'sale', 'spesa', 'expense'])) {
            // transaction type = vendita || spesa
            $guadagnoRealizzato = 0.0;
            $cryptoInfo[$tx['crypto']]['balance'] -= $tx['amount'];

            if (intval(date('Y', strtotime($tx['date']))) === $fiscalYear) {
                if ((in_array($tx['type'], ['vendita', 'sale']) && $tx['value'] === $tx['plusvalenza']) || in_array($tx['type'], ['spesa', 'expense'])) {
                    // if the transaction is a sell of an earned crypto or an expense, find the relative purchase transaction
                    $purchaseTx = null;
                    foreach ($transactions AS $tx2) {
                        if ($tx2['id'] === $tx['tx_acquisto']) {
                            $purchaseTx = $tx2;
                            break;
                        }
                    }
                    if ($purchaseTx === null) {
                        die('Error! Cannot find purchase transaction for ' . $tx['id']);
                    }

                    $exchange = $tx['exchange'];
                    $earnExchange = $purchaseTx['exchange'];

                    if (in_array($tx['type'], ['vendita', 'sale'])) {
                        // the transaction if the sell of an earned crypto; calcluate the "guadagno realizzato" and sync the values in $earnings array
                        $guadagnoRealizzato = $tx['amount'] * getCryptoPrice($purchaseTx['crypto'], date('Y-m-d', strtotime($purchaseTx['date'])));
                        if (intval(date('Y', strtotime($purchaseTx['date']))) === $fiscalYear) {
                            $earnings['rac'][$purchaseTx['categoria']] += $guadagnoRealizzato;
                            $earnings['nr'][$purchaseTx['categoria']] -= $guadagnoRealizzato;
                            $earnings['rac_d'][$purchaseTx['categoria']][$earnExchange] += $guadagnoRealizzato;
                            $earnings['nr_d'][$purchaseTx['categoria']][$earnExchange] -= $guadagnoRealizzato;
                        }
                        else {
                            $earnings['rap'][$purchaseTx['categoria']] += $guadagnoRealizzato;
                            $earnings['rap_d'][$purchaseTx['categoria']][$earnExchange] += $guadagnoRealizzato;
                        }

                        $earnings['plusvalenze'] -= $guadagnoRealizzato;
                        incrementExchangeVolume($exchange, $tx['value']);
                    } elseif (in_array($tx['type'], ['expense', 'spesa'])) {
                        // the transaction is an expense
                        if ($purchaseTx['value'] > 0) {
                            // the purchase is just a regular purchase
                            $purchaseCost = ($purchaseTx['value'] / $purchaseTx['amount']) * $tx['amount'];
                        } else {
                            // the purchase is actually an earn; calcluate the "guadagno realizzato" and sync the values in $earnings array
                            $purchaseCost = $tx['amount'] * getCryptoPrice($purchaseTx['crypto'], date("Y-m-d", strtotime($purchaseTx['date'])));
                            if (intval(date('Y', strtotime($purchaseTx['date']))) === $fiscalYear) {
                                $earnings['rac'][$purchaseTx['categoria']] += $purchaseCost;
                                $earnings['nr'][$purchaseTx['categoria']] -= $purchaseCost;
                                $earnings['rac_d'][$purchaseTx['categoria']][$earnExchange] += $purchaseCost;
                                $earnings['nr_d'][$purchaseTx['categoria']][$earnExchange] -= $purchaseCost;
                            }
                            else {
                                $earnings['rap'][$purchaseTx['categoria']] += $purchaseCost;
                                $earnings['rap_d'][$purchaseTx['categoria']][$earnExchange] += $purchaseCost;
                            }
                        }

                        $sellValue = $tx['amount'] * getCryptoPrice($tx['crypto'], date("Y-m-d", strtotime($tx['date'])));
                        $earnings['plusvalenze'] += ($sellValue - $purchaseCost);
                        $totalTakings += $sellValue;
                        $totalCosts += $purchaseCost;

                        incrementExchangeVolume($exchange, $sellValue);
                    }
                }

                if (in_array($tx['type'], ['vendita', 'sale'])) {
                    // fix the capital gain and other values for sales
                    $earnings['plusvalenze'] += $tx['plusvalenza'];
                    $totalInvestment -= $tx['value'];
                    $totalTakings += $tx['value'];
                    $totalCosts += ($tx['value'] - $tx['plusvalenza'] + $guadagnoRealizzato);
                }
            }
        } else {
            die('Error! Transaction type is invalid for ' . $tx['id']);
        }
    }

    if ($i === 0) {
        $daysInYear = date('z', mktime(0, 0, 0, 12, 31, $fiscalYear));
        foreach ($cryptoInfo AS $crypto) {
            for ($day = 0; $day < $daysInYear; $day++) {
                getCryptoPrice($crypto['symbol'], date('Y-m-d', DateTime::createFromFormat('Y z', $fiscalYear . ' ' . $day)->getTimestamp()));
            }
        }
        fetchGlobalCryptoData();
    }
}

// set start of year balance for cryptocurrencies if $fiscalYear is the firt year of owning
foreach (array_keys($cryptoInfo) AS $cryptoKey) {
    if ($cryptoInfo[$cryptoKey]['start_of_year_balance_eur'] == -1) {
        $cryptoInfo[$cryptoKey]['balance_start'] = $cryptoInfo[$cryptoKey]['balance'];
        $cryptoInfo[$cryptoKey]['start_of_year_balance_eur'] = $cryptoInfo[$cryptoKey]['balance'] * $cryptoInfo[$cryptoKey]['value_start'];
    }
}

// calculate the daily values for the remaing days to the end of the year
$daysInYear = date('z', mktime(0, 0, 0, 12, 31, $fiscalYear));
while ($currentDayOfYear <= $daysInYear) {
    foreach (array_keys($cryptoInfo) AS $cryptoKey) {
        if (!isset($dailyTotalValues[$cryptoKey])) {
            $dailyTotalValues[$cryptoKey] = [];
        }
        $dailyTotalValues[$cryptoKey][$currentDayOfYear] = $cryptoInfo[$cryptoKey]['balance'];
        $cryptoInfo[$cryptoKey]['sum_balance'] += $cryptoInfo[$cryptoKey]['balance'];
        if ($cryptoInfo[$cryptoKey]['balance'] > $cryptoInfo[$cryptoKey]['max_balance']) {
            $cryptoInfo[$cryptoKey]['max_balance'] = $cryptoInfo[$cryptoKey]['balance'];
        }
    }
    $currentDayOfYear++;
}

// calculate additional info for each cryptocurrency
foreach (array_keys($cryptoInfo) AS $cryptoKey) {
    if ($cryptoInfo[$cryptoKey]['sum_balance'] > 0) {
        $cryptoInfo[$cryptoKey]['average_balance'] = $cryptoInfo[$cryptoKey]['sum_balance'] / ($daysInYear + 1);
        $cryptoInfo[$cryptoKey]['average_balance_eur'] = $cryptoInfo[$cryptoKey]['average_balance'] * $cryptoInfo[$cryptoKey]['value_end'];
        $cryptoInfo[$cryptoKey]['end_of_year_balance_eur'] = $cryptoInfo[$cryptoKey]['balance'] * $cryptoInfo[$cryptoKey]['value_end'];
        $cryptoInfo[$cryptoKey]['max_balance_eur'] = $cryptoInfo[$cryptoKey]['max_balance'] * $cryptoInfo[$cryptoKey]['value_end'];
        $cryptoInfo[$cryptoKey]['max_balance_eur_inizio_anno'] = $cryptoInfo[$cryptoKey]['max_balance'] * $cryptoInfo[$cryptoKey]['value_start'];
    }
}

// sort the cryptocurrencies base on its average EUR balance
usort($cryptoInfo, function ($a, $b) {
    return $a['average_balance_eur'] < $b['average_balance_eur'];
});

// arrange and sort the earnings
$allEarnings = array_keys($earnings['rap']) + array_keys($earnings['rac']) + array_keys($earnings['nr']);
sort($allEarnings);

// arrange and sort the detailed earnings
$detailedEarnings = [];
if (isset($earnings['rap_d'])) {
    ksort($earnings['rap_d']);
    foreach ($earnings['rap_d'] AS $categoryName => $categoryValues) {
        ksort($categoryValues);
        $detailedEarnings[$categoryName] = [];
        foreach (array_keys($categoryValues) AS $exchange) {
            $detailedEarnings[$categoryName][$exchange]['rap'] = $earnings['rap_d'][$categoryName][$exchange];
            $detailedEarnings[$categoryName][$exchange]['rac'] = $earnings['rac_d'][$categoryName][$exchange];
            $detailedEarnings[$categoryName][$exchange]['nr'] = $earnings['nr_d'][$categoryName][$exchange];
        }
    }
}

$dailyTotalValuesLegal = [];     // value with prices at the first day of the year (art. 67 TUIR)
$dailyTotalValuesReal = [];      // value with the real daily prices
$dailyTotalValuesEOY = [];       // value with the prices at the end of the year

// initialize arrays
for ($i = 0; $i <= $daysInYear; $i++) {
    $dailyTotalValuesLegal[$i] = 0.0;
    $dailyTotalValuesReal[$i] = 0.0;
    $dailyTotalValuesEOY[$i] = 0.0;
}

// calculate the daily values
foreach ($dailyTotalValues AS $ticker => $days) {
    foreach ($days AS $day => $value) {
        if ($value > 0) {
            $dailyTotalValuesLegal[$day] += $value * $cryptoInfo[getCryptoKey($ticker)]['value_start'];
            $dailyTotalValuesEOY[$day] += $value * $cryptoInfo[getCryptoKey($ticker)]['value_end'];
            $dailyTotalValuesReal[$day] += $value * getCryptoPrice($ticker, date('Y-m-d', DateTime::createFromFormat('Y z', $fiscalYear . ' ' . $day)->getTimestamp()));
        }
    }
}

// round the daily values
for ($i = 0; $i <= $daysInYear; $i++) {
    $dailyTotalValuesLegal[$i] = round($dailyTotalValuesLegal[$i], 2);
    $dailyTotalValuesEOY[$i] = round($dailyTotalValuesEOY[$i], 2);
    $dailyTotalValuesReal[$i] = round($dailyTotalValuesReal[$i], 2);
}

// calculate the total values (sum of each cryptocurrency)
$totalValues = [
    'saldo_inizio' => 0.0,
    'saldo_fine' => 0.0,
    'controvalore_inizio' => 0.0,
    'controvalore_fine' => 0.0,
    'giacenza_media' => 0.0,
    'valore_massimo' => 0.0,
    'valore_massimo_inizio_anno' => 0.0     // (art. 67 TUIR), 1.000.000 lire theshold
];

foreach ($cryptoInfo as $crypto) {
    if ($crypto['value_start'] === $crypto['value_end'] && $crypto['value_start'] === 0.0) {
        // skip cryptocurrencies without price
        continue;
    }

    if ($crypto['sum_balance'] > 0) {
        $totalValues['saldo_inizio'] += $crypto['balance_start'];
        $totalValues['saldo_fine'] += $crypto['balance'];
        $totalValues['controvalore_inizio'] += $crypto['start_of_year_balance_eur'];
        $totalValues['controvalore_fine'] += $crypto['end_of_year_balance_eur'];
        $totalValues['giacenza_media'] += $crypto['average_balance_eur'];
        $totalValues['valore_massimo'] += $crypto['max_balance_eur'];
        $totalValues['valore_massimo_inizio_anno'] += $crypto['max_balance_eur_inizio_anno'];
    }
}

// €51645,69 = 1.000.00 LIRE, art. 67 TUIR
$exceeded51kThreshold = $totalValues['valore_massimo_inizio_anno'] > 51645.69;

$daysList = [];
for ($i = 0; $i <= $daysInYear; $i++) {
    $daysList[] = date('d/m', DateTime::createFromFormat('Y z', $fiscalYear . ' ' . $i)->getTimestamp());
}

arsort($exchangeVolumes);
foreach ($exchangeVolumes AS $exchange => $volume) {
    if (preg_match('/^[-]*$/', $exchange)) {
        unset($exchangeVolumes[$exchange]);
    }
}

require 'render.php';
