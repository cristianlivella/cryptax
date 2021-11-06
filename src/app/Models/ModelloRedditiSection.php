<?php

namespace CrypTax\Models;

use CrypTax\Helpers\VersionHelper;

abstract class ModelloRedditiSection
{
    protected static function addHeaderFooter($pdf, $fiscalYear, $pfFirstPage = true) {
        if ($pfFirstPage) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetXY(150, 7);
            $pdf->Write(0, 'PERIODO D\'IMPOSTA ' . $fiscalYear);

            $pdf->SetTextColor(0, 66, 112);
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->SetXY(42, 32);
            $pdf->Write(0, ($fiscalYear + 1));

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Courier', '', 10);
            $pdf->SetXY(160, 13);
            $pdf->Write(0, 'Facsimile CrypTax');
        }

        $pdf->SetFont('Courier', '', 9);
        $pdf->SetXY(10, 264);
        $pdf->Write(0, 'Generato da CrypTax - ' . VersionHelper::getVersion() . ' - github.com/cristianlivella/cryptax');

        $pdf->SetFont('Courier', '', 8);
        $pdf->SetXY(10, 268);
        $pdf->Write(0, 'Modello generato in data ' . date('d/m/Y') . ' alle ore ' . date('H:i:s') . '.');

        $pdf->SetXY(10, 276);
        $pdf->Write(0, 'ATTENZIONE: CrypTax e Cristian Livella non si assumono nessuna responsabilita riguardo la correttezza');

        $pdf->SetXY(10, 280);
        $pdf->Write(0, 'e la completezza dei dati riportati in questo modulo. Il software offre un aiuto nel calcolo');

        $pdf->SetXY(10, 284);
        $pdf->Write(0, 'delle plusvalenze e nella compilazione dei modelli, ma e\' responsabilita\' del contribuente di verificarne');

        $pdf->SetXY(10, 288);
        $pdf->Write(0, 'la correttazza e la compatibilita\' con la propria situazione finanziaria complessiva.');
    }

    protected static function writeRTL($pdf, $x, $y, $text) {
        $pdf->SetXY($x - strlen($text) * 2.1, $y);
        $pdf->Write(0, $text);
    }
}
