<?php

namespace CrypTax\Models;

use CrypTax\Utils\DateUtils;
use CrypTax\Utils\NumberUtils;
use CrypTax\Utils\VersionUtils;
use CrypTax\Utils\CryptoInfoUtils;

class ReportWrapper
{
    private Report $report;

    public function __construct($transactionsFileContent, $exchangeInterestTypes = []) {
        $this->report = new Report($transactionsFileContent, $exchangeInterestTypes);
    }

    public function getSummary($rawValues = false) {
        $currentYear = $this->report->getCurrentYear();

        if (!$currentYear) {
            $currentYear = DateUtils::getCurrentYear();
        }

        $reportSummaries = [];
        $exchangeInterestList = [];
        $yearsList = [];

        for ($year = $this->report->getFirstYear(); $year <= $this->report->getLastYear(); $year++) {
            $this->report->elaborateReport($year);
            $summary = $this->report->getSummary(true);

            if ($summary['total_values']['average_value'] > 0.01) {
                $reportSummaries['years'][$year] = $summary;

                $exchangeInterestList = array_merge($exchangeInterestList, $reportSummaries['years'][$year]['interest_exchanges']);
                $yearsList[] = $year;
            }
        }

        $reportSummaries = $this->calculateCapitalLossesCompensation($reportSummaries);
        $reportSummaries['interest_exchanges'] = $exchangeInterestList;
        $reportSummaries['years_list'] = $yearsList;

        $this->report->elaborateReport($currentYear);

        if ($rawValues) {
            return $reportSummaries;
        } else {
            return NumberUtils::recursiveFormatNumbers($reportSummaries, 0, true);
        }
    }

    public function elaborateReport($year) {
        $this->report->elaborateReport($year);
    }

    public function getInfoForRender() {
        return $this->report->getInfoForRender();
    }

    public function getReport($year) {
        $this->elaborateReport($year);

        $loader = new \Twig\Loader\FilesystemLoader('resources/views/');
        $twig = new \Twig\Environment($loader);

        $baseUrl = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if (strrpos($baseUrl, '?')) {
            $baseUrl = substr($baseUrl, 0, strrpos($baseUrl, '?'));
        }

        $years = array_keys($this->getSummary());

        $yearSelectors = [];

        foreach ($years AS $thisYear) {
            $yearSelectors[$thisYear] = $baseUrl . '?' . http_build_query([
                'year' => $thisYear
            ]);
        }

        $forms = [];

        if ($this->report->shouldFillModelloRedditi()) {
            $forms['pf'] = $baseUrl . '?' . http_build_query([
                'year' => $year,
                'action' => 'pdf_modello_redditi'
            ]);
        }

        if (($this->report->shouldFillRT() && $this->report->getCapitalGainsTax() > 0) || $this->report->shouldFillRM()) {
            $forms['f24'] = $baseUrl . '?' . http_build_query([
                'year' => $year,
                'action' => 'pdf_modello_f24'
            ]);
        }

        return $twig->render('report.html', $this->report->getInfoForRender() + [
            'header' => HEADER,
            'years' => $yearSelectors,
            'forms' => $forms,
            'form_names' => [
                'pf' => 'Redditi Persone Fisiche ' . ($year + 1),
                'f24' => 'Modello di pagamento F24'
            ],
            'software_version' => VersionUtils::getVersion(),
            'date' => date('d/m/Y'),
            'time' => date('H:i:s'),
            'warnings' => CryptoInfoUtils::getWarnings(intval($year)),
            'mode_private' => !defined('MODE') || MODE === 'private'
        ]);
    }

    public function getModelloRedditi($year, $compensateCapitalLosses = true) {
        $this->elaborateReport($year);

        $info = $this->report->getInfoForModelloRedditi() + [
            'rl' => $this->getSectionRlInfo($year),
            'rw' => $this->report->getModelloRedditiSectionRwInfo(),
            'rt' => $this->getSectionRtInfo($year, $compensateCapitalLosses),
            'rm' => $this->getSectionRmInfo($year)
        ];

        $modelloRedditi = new ModelloRedditi($info);
        return $modelloRedditi->getPdf();
    }

    public function getModelloF24($year, $compensateCapitalLosses = true) {
        $this->elaborateReport($year);

        $taxes = [];

        if ($this->report->shouldFillRT()) {
            $rtInfo = $this->getSectionRtInfo($year, true);
            $capitalGainsTax = $compensateCapitalLosses ? $rtInfo['capital_gains_compensated_tax'] : $rtInfo['capital_gains_tax'];

            if ($capitalGainsTax > 0) {
                $taxes[] = ['code' => 1100, 'amount' => $capitalGainsTax];
            }
        }

        if ($this->report->shouldFillRM()) {
            $rmInfo = $this->getSectionRmInfo($year);
            $taxes[] = ['code' => 1242, 'amount' => $rmInfo['tax']];
        }

        $modelloF24 = new ModelloF24($year, $taxes);
        return $modelloF24->getPdf();
    }

    public function getSectionRtInfo($year, $compensateCapitalLosses) {
        $summary = $this->getSummary();

        $this->elaborateReport($year);
        $capitalGains = round($this->report->getCapitalGains());

        $info = [
            'total_incomes' => round($this->report->currentYearPurchaseCost) + $capitalGains,
            'total_costs' => round($this->report->currentYearPurchaseCost),
            'capital_gains' => $capitalGains > 0 ? $capitalGains : '',
            'capital_losses' => $capitalGains < 0 ? abs($capitalGains) : '',
            'capital_losses_previous_years' => max(0, $capitalGains - $summary['years'][$year]['capital_gains_compensated']),
            'capital_gains_compensated' => round($summary['years'][$year]['capital_gains_compensated']),
            'capital_gains_compensated_tax' => max(0, round(round($summary['years'][$year]['capital_gains_compensated']) * Report::CAPITAL_GAINS_TAX_RATE)),
            'capital_gains_tax' => max(0, round($capitalGains * Report::CAPITAL_GAINS_TAX_RATE)),
            'compensate_capital_losses' => $compensateCapitalLosses,
            'remaining_capital_losses' => []
        ];

        for ($i = 4; $i >= 0; $i--) {
            $info['remaining_capital_losses'][$year - $i] = $summary['years'][$year - $i]['remaining_capital_losses'][$year] ?? 0;
        }

        return $info;
    }

    public function getSectionRlInfo($year) {
        $this->elaborateReport($year);

        return [
            'interests' => round($this->report->getInterests(EarningsBag::INTEREST_RL))
        ];
    }

    public function getSectionRmInfo($year) {
        $this->elaborateReport($year);

        return [
            'interests' => round($this->report->getInterests(EarningsBag::INTEREST_RM)),
            'tax_rate' => round(Report::INTERESTS_EARNING_TAX_RATE * 100),
            'tax' => round(round($this->report->getInterests(EarningsBag::INTEREST_RM)) * Report::INTERESTS_EARNING_TAX_RATE)
        ];
    }

    private function calculateCapitalLossesCompensation($summaries) {
        $totalCompensation = 0;

        foreach ($summaries['years'] AS $year => &$summary) {
            $summary['remaining_capital_losses'][$year] = 0;
            $summary['capital_gains_compensated'] = 0;

            if (!$summary['no_tax_area_threshold_exceeded'] || $year < 2016) {
                // do nothing if the no-tax area threshold has not been exceeded in this year or if fiscal year is before 2016
                // TODO: controllare se $year < 2016 porta al comportamento corretto
                continue;
            }

            if ($summary['capital_gains_and_airdrop'] >= 0) {
                $summary['capital_gains_compensated'] = round($summary['capital_gains_and_airdrop']);
            } else {
                // if capital gains are less than 0, then they are actually capital losses
                $summary['remaining_capital_losses'][$year] = round(abs($summary['capital_gains_and_airdrop']));
            }

            // check if we can compensate capital gains with capital losses of the previous 4 years
            for ($i = 4; $i > 0; $i--) {
                if ($summary['capital_gains_compensated'] > 0 && ($summaries['years'][$year - $i]['remaining_capital_losses'][$year - 1] ?? 0) > 0) {
                    // if in the year [$year - 1] there are remaining capital losses, we reduce the capital gains of this year
                    $compensation = min($summary['capital_gains_compensated'], $summaries['years'][$year - $i]['remaining_capital_losses'][$year]);
                    $summary['capital_gains_compensated'] -= $compensation;
                    $summaries['years'][$year - $i]['remaining_capital_losses'][$year] -= $compensation;
                    $totalCompensation += $compensation;

                    // update the remaining capital losses for any following years
                    for ($j = $year; $j <= $year - $i + 4; $j++) {
                        $summaries['years'][$year - $i]['remaining_capital_losses'][$j] = $summaries['years'][$year - $i]['remaining_capital_losses'][$year];
                    }
                }
            }

            // set the remaining capital losses for the following 4 years
            for ($i = 0; $i <= 4; $i++) {
                $summary['remaining_capital_losses'][$year + $i] = $summary['remaining_capital_losses'][$year];
            }
        }

        $summaries['total_compensation'] = $totalCompensation;

        return $summaries;
    }
}
