<?php

namespace CrypTax\Models;

use CrypTax\Helpers\VersionHelper;

use setasign\Fpdi\Fpdi;

class ModelloRedditiRw extends ModelloRedditiSection
{
    public static function fill($pdf, $info, $fiscalYear) {
        $countPages = $pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-RW.pdf');

        for ($page = 1; $page <= $countPages; $page++) {
            $templateId = $pdf->importPage($page);

            $pdf->addPage();
            $pdf->useTemplate($templateId);

            self::addHeaderFooter($pdf, $fiscalYear);

            if ($page === 1) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Courier', 'B', 10);

                // 1 - Codice titolo possesso
                $pdf->SetXY(46, 57.8);
                $pdf->Write(0, '1');

                // 2 - Vedere istruzioni
                $pdf->SetXY(61, 57.8);
                $pdf->Write(0, '2');

                // 3 - Codice individuazione bene
                $pdf->SetXY(76, 57.8);
                $pdf->Write(0, '14');

                // 4 - Quota di possesso
                $pdf->SetXY(106, 57.8);
                $pdf->Write(0, '100');

                // 5 - Criterio determinazione valore
                $pdf->SetXY(121, 57.8);
                $pdf->Write(0, '1');

                // 6 - Valore iniziale
                self::writeRTL($pdf, 159, 57.8, round($info['rw']['initial_value']));

                // 7 - Valore finale
                self::writeRTL($pdf, 192.3, 57.8, round($info['rw']['final_value']));

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
                $pdf->SetXY(191, 82.6);
                $pdf->Write(0, 'X');

            }
        }
    }
}
