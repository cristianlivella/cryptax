<?php

namespace CrypTax\Models;

abstract class ModelloRedditiRm
{
    public static function fill($pdf, $info, $fiscalYear) {
        $countPages = $pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-RM.pdf');

        for ($page = 1; $page <= $countPages; $page++) {
            $templateId = $pdf->importPage($page);

            $pdf->addPage();
            $pdf->useTemplate($templateId);
            $pdf->addHeaderFooter($fiscalYear, $page === 1);

            if ($page === 1) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Courier', 'B', 10);

                // RM12.1 - Tipo
                $pdf->writeRTL(50, 121, 'G');

                // RM12.3 - Ammontare reddito
                $pdf->writeRTL(94.5, 121, $info['rm']['interests']);

                // RM12.4 - Aliquota
                $pdf->writeRTL(108, 121, $info['rm']['tax_rate']);

                // RM12.6 - Imposta sostitutiva dovuta
                $pdf->writeRTL(159, 121, $info['rm']['tax']);
            }
        }
    }
}
