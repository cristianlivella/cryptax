<?php

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    die('This script cannot be called directly!');
}

?>

<html>
    <head>
        <title>CrypTax</title>
        <link
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
            integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z"
            rel="stylesheet"
            crossorigin="anonymous">
        <link href="./style.css" rel="stylesheet">
    </head>
    <body>
        <h2><?= HEADER ?></h2>
        <h4>Report criptovalute anno <?= $fiscalYear ?></h4>

        <table class='table table-bordered table-sm'>
            <tr>
                <th colspan='2'>Criptovaluta</th>
                <th colspan='2'>Prezzo 01/01/<?= $fiscalYear ?> <sub>1)</sub></th>
                <th colspan='2'>Prezzo 31/12/<?= $fiscalYear ?> <sub>2)</sub></th>
                <th colspan='2'>Saldo 01/01/<?= $fiscalYear ?></th>
                <th colspan='2'>Controvalore 01/01/<?= $fiscalYear ?></th>
                <th colspan='2'>Saldo 31/12/<?= $fiscalYear ?></th>
                <th colspan='2'>Controvalore 31/12/<?= $fiscalYear ?></th>
                <th colspan='2'>Giacenza media <sub>3)</sub></th>
                <th colspan='2'>Valore massimo <sub>3)</sub></th>
            </tr>

            <?php
            foreach ($cryptoInfo as $thisCrypto) {
                if ($thisCrypto['value_start'] === $thisCrypto['value_end'] && $thisCrypto['value_start'] === 0.0) {
                    // skip cryptocurrencies without price
                     continue;
                }
                ?>
                <tr>
                    <td><b><?= $thisCrypto['name'] ?></b></td>
                    <td><b><?= $thisCrypto['symbol'] ?></b></td>
                    <td class='price'><?= number_format($thisCrypto['value_start'], 3, ',', '.') ?></td>
                    <td class='unit'>€</td>
                    <td class='price'><?= number_format($thisCrypto['value_end'], 3, ',', '.') ?></td>
                    <td class='unit'>€</td>
                    <td class='price'><?= formatBalance($thisCrypto['balance_start'], $thisCrypto['value_start']) ?></td>
                    <td class='unit'>¤</td>
                    <td class='price'><?= number_format($thisCrypto['start_of_year_balance_eur'], 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                    <td class='price'><?= formatBalance($thisCrypto['balance'], $thisCrypto['value_end']) ?></td>
                    <td class='unit'>¤</td>
                    <td class='price'><?= number_format($thisCrypto['end_of_year_balance_eur'], 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                    <td class='price'><?= number_format($thisCrypto['average_balance_eur'], 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                    <td class='price'><?= number_format($thisCrypto['max_balance_eur'], 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <?php
            }
            ?>

            <tr>
                <td colspan='2'><b>TOTALE</b></td>
                <td class='price' colspan='2'>n.a.</td>
                <td class='price' colspan='2'>n.a.</td>
                <td class='price' colspan='2'>n.a.</td>
                <td class='price'><b><?= number_format($totalValues['controvalore_inizio'], 2, ',', '.') ?></b></td>
                <td class='unit'><b>€</b></td>
                <td class='price' colspan='2'>n.a.</td>
                <td class='price'><b><?= number_format($totalValues['controvalore_fine'], 2, ',', '.') ?></b></td>
                <td class='unit'><b>€</b></td>
                <td class='price'><b><?= number_format($totalValues['giacenza_media'], 2, ',', '.') ?></b></td>
                <td class='unit'><b>€</b></td>
                <td class='price'><b><?= number_format($totalValues['valore_massimo'], 2, ',', '.') ?></b></td>
                <td class='unit'><b>€</b></td>
            </tr>
        </table>

        <div class="pagebreak"> </div>

        <div class="column leftColumn" style="">
            <table class='table table-bordered table-sm' style="max-width: 620px; margin: 0 auto;">
                <tr>
                    <th colspan='8'>Guadagni</th>
                </tr>
                <tr>
                    <th colspan='1' rowspan='2'>Tipologia</th>
                    <th colspan='4'>Realizzati </th>
                    <th colspan='2' rowspan='2'>Non realizzati <sub>6)</sub></th>
                </tr>
                <tr>
                    <th colspan='2'>anni precedenti <sub>5)</sub></th>
                    <th colspan='2'>anno corrente</th>
                </tr>
                <tr>
                    <td>Plusvalenze</td>
                    <td class='price' colspan='2'>n.a.</td>
                    <td class='price'><?= number_format($earnings['plusvalenze'], 2, ',', '.') ?></td>
                    <td class='unit'>€</td><td class='price' colspan='2'>n.a.</td>
                </tr>

                <?php
                foreach ($allEarnings AS $category) {
                    ?>
                    <tr>
                        <td><?= ucfirst($category) ?></td>
                        <td class='price'><?= number_format($earnings['rap'][$category], 2, ',', '.') ?></td>
                        <td class='unit'>€</td>
                        <td class='price'><?= number_format($earnings['rac'][$category], 2, ',', '.') ?></td>
                        <td class='unit'>€</td>
                        <td class='price'><?= number_format($earnings['nr'][$category], 2, ',', '.') ?></td>
                        <td class='unit'>€</td>
                    </tr>
                    <?php
                }
                ?>

            </table>

            <?php
            foreach ($detailedEarnings AS $category => $categoryEarnings) {
                ?>
                <table class='table table-bordered table-sm' style="max-width: 620px; margin: 0 auto; margin-top: 16px;">
                    <tr>
                        <th colspan='8'>Dettaglio <?= $category ?></th>
                    </tr>
                    <tr>
                        <th colspan='1' rowspan='2'>Provenienza</th>
                        <th colspan='4'>Realizzati </th>
                        <th colspan='2' rowspan='2'>Non realizzati <sub>6)</sub></th>
                    </tr>
                    <tr>
                        <th colspan='2'>anni precedenti <sub>5)</sub></th>
                        <th colspan='2'>anno corrente</th>
                    </tr>

                    <?php
                    foreach ($categoryEarnings AS $exchange => $values) {
                        ?>
                        <tr>
                            <td><?= $exchange ?></td>
                            <td class='price'><?= number_format($values['rap'], 2, ',', '.') ?></td>
                            <td class='unit'>€</td>
                            <td class='price'><?= number_format($values['rac'], 2, ',', '.') ?></td>
                            <td class='unit'>€</td>
                            <td class='price'><?= number_format($values['nr'], 2, ',', '.') ?></td>
                            <td class='unit'>€</td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php
            }
            ?>
        </div>

        <div class="column rightColumn">
            <table class="table table-bordered table-sm" style="max-width: 460px; margin: 0 auto;">
                <tr>
                    <th style="text-align: left">Investimenti anno corrente <sub>4)</sub></th>
                    <td class='price'><?= number_format($totalInvestment, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th colspan="3"></th>
                </tr>
                <tr>
                    <th style="text-align: left">Totale dei corrispettivi <sub>RT21</sub></th>
                    <td class='price'><?= number_format($totalTakings, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Totale dei costi o dei valori di acquisto <sub>RT22</sub></th>
                    <td class='price'><?= number_format($totalCosts, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Plusvalenze</th>
                    <td class='price'><?= number_format($totalTakings - $totalCosts, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Plusvalenze + airdrop <sub>RT23</sub> <sub>7)</sub></th>
                    <td class='price'><?= number_format($totalTakings - $totalCosts + ($earnings['rac']['airdrop'] ?? 0) + ($earnings['nr']['airdrop'] ?? 0), 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Imposta sostitutiva <sub>RT27</sub></th>
                    <td class='price'><?= number_format(($totalTakings - $totalCosts + ($earnings['rac']['airdrop'] ?? 0) + ($earnings['nr']['airdrop'] ?? 0)) * 0.26, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Superata soglia 51.645,69 €</sub></th>
                    <td style="text-align: center" colspan='2'><?= $exceeded51kThreshold ? 'sì' : 'no' ?></td>
                </tr>
                <tr><th colspan="3"></th></tr>
                <tr>
                    <th style="text-align: left">Redditi di capitale <sub>RM12.3</sub> <sub>8)</sub></th>
                    <td class='price'><?= number_format(($earnings['rac']['interessi'] ?? 0) + ($earnings['nr']['interessi'] ?? 0), 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
                <tr>
                    <th style="text-align: left">Imposta sostitutiva <sub>RM12.6</sub></th>
                    <td class='price'><?= number_format((($earnings['rac']['interessi'] ?? 0) + ($earnings['nr']['interessi'] ?? 0)) * 0.26, 2, ',', '.') ?></td>
                    <td class='unit'>€</td>
                </tr>
            </table>

            <div class="chart" style="width: 99%; height: 400px; margin-top: 30px;">
                <canvas id="chart2" width="100%" height="100%"></canvas>
            </div>
        </div>

        <div style="float: none; clear: both" />

        <div class="chart half left" style="width: 100%; height: 400px; margin-top: 8px; margin-bottom: 16px;">
            <canvas id="chart1" width="100%" height="100%"></canvas>
        </div>

        <p>
            1) fonte: cryptohistory.one<br />
            nei casi dove non è disponibile il prezzo al giorno 01/01/<?= $fiscalYear ?> è indicato il primo prezzo disponibile nell'anno <?= $fiscalYear ?>
        </p>
        <p>
            2) fonte: cryptohistory.one
        </p>
        <p>
            3) calcolato secondo il prezzo al giorno 31/12/<?= $fiscalYear ?>
        </p>
        <p>
            4) somma dei costi d'acquisto - somma dei corrispettivi di cessione
        </p>
        <p>
            5) guadagni percepiti negli anni precedenti, ma venduti ovvero scambiati con un'altra criptovaluta nell'anno corrente
        </p>
        <p>
            6) guadagni percepiti nell'anno corrente che non sono ancora stati venduti ovvero scambiati con un'altra criptovaluta
        </p>
        <p>
            7) somma delle plusvalenze e degli airdrop ricevuti nell'anno corrente, realizzati e non realizzati
        </p>
        <p>
            8) interessi ricevuti nell'anno corrente, realizzati e non realizzati
        </p>

        <script>
            const fiscalYear = <?= json_encode($fiscalYear) ?>;
            const daysList = <?= json_encode($daysList) ?>;
            const dailyTotalValuesLegal = <?= json_encode($dailyTotalValuesLegal) ?>;
            const dailyTotalValuesEOY = <?= json_encode($dailyTotalValuesEOY) ?>;
            const dailyTotalValuesReal = <?= json_encode($dailyTotalValuesReal) ?>;
            const exchanges = <?= json_encode(array_keys($exchangeVolumes)) ?>;
            const exchangeVolumes = <?= json_encode(array_values($exchangeVolumes)) ?>;
        </script>

        <script src="https://www.chartjs.org/dist/2.9.4/Chart.min.js" integrity="sha256-t9UJPrESBeG2ojKTIcFLPGF7nHi2vEc7f5A2KpH/UBU=" crossorigin="anonymous"></script>
        <script src="https://google.github.io/palette.js/palette.js" integrity="sha256-5f158MWiiHKxtoGF9P+WFLMLh3bMthtMx58ayBvTF38=" crossorigin="anonymous"></script>
        <script src="script.js"></script>
    </body>
</html>
