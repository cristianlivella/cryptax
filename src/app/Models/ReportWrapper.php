<?php

namespace CrypTax\Models;

use CrypTax\Utils\NumberUtils;

class ReportWrapper
{
    private Report $report;

    public function __construct($transactionsFileContent) {
        $this->report = new Report($transactionsFileContent);
    }

    public function getSummary() {
        $reportSummaries = [];
        $this->report->getFirstYear();

        for ($year = $this->report->getFirstYear(); $year <= $this->report->getLastYear(); $year++) {
            $this->report->elaborateReport($year);
            $summary = $this->report->getSummary(true);

            if ($summary['total_values']['average_value'] > 0.01) {
                $reportSummaries[$year] = $summary;
            }
        }
        $reportSummaries = $this->calculateCapitalLossesCompensation($reportSummaries);

        return NumberUtils::recursiveFormatNumbers($reportSummaries, 0, true);
    }

    public function elaborateReport($year) {
        $this->report->elaborateReport($year);
    }

    public function getInfoForRender() {
        return $this->report->getInfoForRender();
    }

    public function getModelloRedditi($year) {
        $this->elaborateReport($year);

        $info = $this->report->getInfoForModelloRedditi() + [
            'rw' => $this->report->getModelloRedditiSectionRwInfo(),
            'rt' => $this->getSectionRtInfo($year, true),
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
            $taxes[] = ['code' => 1100, 'amount' => $capitalGainsTax];
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
            'total_incomes' => round($this->report->currentYearIncome),
            'total_costs' => round($this->report->currentYearPurchaseCost),
            'capital_gains' => $capitalGains > 0 ? $capitalGains : '',
            'capital_losses' => $capitalGains < 0 ? abs($capitalGains) : '',
            'capital_losses_previous_years' => max(0, $capitalGains - $summary[$year]['capital_gains_compensated']),
            'capital_gains_compensated' => round($summary[$year]['capital_gains_compensated']),
            'capital_gains_compensated_tax' => round(round($summary[$year]['capital_gains_compensated']) * Report::CAPITAL_GAINS_TAX_RATE),
            'capital_gains_tax' => round($capitalGains * Report::CAPITAL_GAINS_TAX_RATE),
            'compensate_capital_losses' => $compensateCapitalLosses,
            'remaining_capital_losses' => []
        ];

        for ($i = 4; $i >= 0; $i--) {
            $info['remaining_capital_losses'][$year - $i] = $summary[$year - $i]['remaining_capital_losses'][$year] ?? 0;
        }

        return $info;
    }

    public function getSectionRmInfo($year) {
        $this->elaborateReport($year);

        return [
            'interests' => round($this->report->getInterests()),
            'tax_rate' => round(Report::INTERESTS_EARNING_TAX_RATE * 100),
            'tax' => round(round($this->report->getInterests()) * Report::INTERESTS_EARNING_TAX_RATE)
        ];
    }

    private function calculateCapitalLossesCompensation($summaries) {
        $totalCompensation = 0;

        foreach ($summaries AS $year => &$summary) {
            $summary['remaining_capital_losses'][$year] = 0;
            $summary['capital_gains_compensated'] = 0;

            if (!$summary['no_tax_area_threshold_exceeded']) {
                // do nothing if the no tax zone threshold has not been exceeded in this year
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
                if ($summary['capital_gains_compensated'] > 0 && ($summaries[$year - $i]['remaining_capital_losses'][$year - 1] ?? 0) > 0) {
                    // if in the year [$year - 1] there are remaining capital losses, we reduce the capital gains of this year
                    $compensation = min($summary['capital_gains_compensated'], $summaries[$year - $i]['remaining_capital_losses'][$year]);
                    $summary['capital_gains_compensated'] -= $compensation;
                    $summaries[$year - $i]['remaining_capital_losses'][$year] -= $compensation;
                    $totalCompensation += $compensation;

                    // update the remaining capital losses for any following years
                    for ($j = $year; $j <= $year - $i + 4; $j++) {
                        $summaries[$year - $i]['remaining_capital_losses'][$j] = $summaries[$year - $i]['remaining_capital_losses'][$year];
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
