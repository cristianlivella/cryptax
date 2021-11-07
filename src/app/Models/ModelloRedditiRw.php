<?php

namespace CrypTax\Models;

abstract class ModelloRedditiRw
{
    public static function fill($pdf, $info, $fiscalYear) {
        $countPages = $pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-RW.pdf');

        for ($page = 1; $page <= $countPages; $page++) {
            $templateId = $pdf->importPage($page);

            $pdf->addPage();
            $pdf->useTemplate($templateId);
            $pdf->addHeaderFooter($fiscalYear, $page === 1);

            if ($page === 1) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Courier', 'B', 10);

                // 1 - Codice titolo possesso
                $pdf->writeXY(46, 57.8, '1');

                // 2 - Vedere istruzioni
                $pdf->writeXY(61, 57.8, '2');

                // 3 - Codice individuazione bene
                $pdf->writeXY(76, 57.8, '14');

                // 4 - Quota di possesso
                $pdf->writeXY(106, 57.8, '100');

                // 5 - Criterio determinazione valore
                $pdf->writeXY(121, 57.8, '1');

                // 6 - Valore iniziale
                $pdf->writeRTL(159, 57.8, round($info['rw']['initial_value']));

                // 7 - Valore finale
                $pdf->writeRTL(192.3, 57.8, round($info['rw']['final_value']));

                // 18 - Vedere istruzioni
                $pdf->SetXY(164, 82.6);
                if ($info['sections_required']['rm'] && $info['sections_required']['rt']) {
                    $pdf->Write(0, '4');
                } elseif ($info['sections_required']['rm']) {
                    $pdf->Write(0, '2');
                } elseif ($info['sections_required']['rt']) {
                    $pdf->Write(0, '3');
                } else {
                    $pdf->Write(0, '5');
                }

                // 20 - Solo monitoraggio
                $pdf->writeXY(191, 82.6, 'X');
            }
        }
    }
}
