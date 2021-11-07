<?php

namespace CrypTax\Models;

abstract class ModelloRedditiRt
{
    public static function fill($pdf, $info, $fiscalYear) {
        $countPages = $pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-RT.pdf');

        for ($page = 1; $page <= $countPages; $page++) {
            $templateId = $pdf->importPage($page);

            $pdf->addPage();
            $pdf->useTemplate($templateId);
            $pdf->addHeaderFooter($fiscalYear, $page === 1);

            if ($page === 1) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Courier', 'B', 10);

                // RT21 - Totale dei corrispettivi
                $pdf->writeRTL(192, 91.2, $info['rt']['total_incomes']);

                // RT22 - Totale dei costi o dei valori di acquisto
                $pdf->writeRTL(192, 95.4, $info['rt']['total_costs']);

                // RT23.1 - Minusvalenze
                $pdf->writeRTL(118.4, 99.6, $info['rt']['capital_losses']);

                // RT23.3 - Plusvalenze
                $pdf->writeRTL(192, 99.6, $info['rt']['capital_gains']);

                if ($info['rt']['compensate_capital_losses']) {
                    // RT24.1 - Eccedenza minusvalenze anni precedenti
                    $pdf->writeRTL(98.4, 103.8, $info['rt']['capital_losses_previous_years']);

                    // RT24.4 - Eccedenza minusvalenze
                    $pdf->writeRTL(192, 103.8, $info['rt']['capital_losses_previous_years']);

                    // RT26 - Differenza
                    $pdf->writeRTL(192, 112.2, $info['rt']['capital_gains_compensated']);

                    // RT27 - Imposta sostitutiva
                    $pdf->writeRTL(192, 116.4, $info['rt']['capital_gains_compensated_tax']);

                    // RT29 - Imposta sostitutiva dovuta
                    $pdf->writeRTL(192, 124.8, $info['rt']['capital_gains_compensated_tax']);

                    // RT93 - Minusvalenze non compensate nell'anno
                    for ($i = 4; $i >= 0; $i--) {
                        $pdf->writeRTL(192 - $i * 27.8, 218, $info['rt']['remaining_capital_losses'][$fiscalYear - $i] ?? 0);
                    }
                } else {
                    // RT26 - Differenza
                    $pdf->writeRTL(192, 112.2, $info['rt']['capital_gains']);

                    // RT27 - Imposta sostitutiva
                    $pdf->writeRTL(192, 116.4, $info['rt']['capital_gains_tax']);

                    // RT29 - Imposta sostitutiva dovuta
                    $pdf->writeRTL(192, 124.8, $info['rt']['capital_gains_tax']);
                }
            }
        }
    }
}
