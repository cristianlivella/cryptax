<?php

namespace CrypTax\Models;

use CrypTax\Utils\VersionUtils;

use setasign\Fpdi\Fpdi;

class PdfDocument extends Fpdi
{
    public function __construct() {
        parent::__construct();

        $this->SetAutoPageBreak(true, 0);
        $this->SetRightMargin(0);
    }

    public function writeXY($x, $y, $text) {
        $this->SetXY($x, $y);
        $this->Write(0, $text);
    }

    public function writeRTL($x, $y, $text) {
        $this->writeXY($x - strlen($text) * 2.1, $y, $text);
    }

    public function addHeaderFooter($fiscalYear, $pfFirstPage = true) {
        if ($pfFirstPage) {
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Helvetica', 'B', 10);
            $this->writeXY(150, 7, 'PERIODO D\'IMPOSTA ' . $fiscalYear);

            $this->SetTextColor(0, 66, 112);
            $this->SetFont('Helvetica', 'B', 14);
            $this->writeXY(43, 32, $fiscalYear + 1);

            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Courier', '', 10);
            $this->writeXY(160, 13, 'Facsimile CrypTax');
        }

        $this->SetFont('Courier', '', 9);
        $this->writeXY(10, 264, 'Generato da CrypTax - ' . VersionUtils::getVersion() . ' - github.com/cristianlivella/cryptax');

        $this->SetFont('Courier', '', 8);
        $this->writeXY(10, 268, 'Modello generato in data ' . date('d/m/Y') . ' alle ore ' . date('H:i:s') . '.');
        $this->writeXY(10, 276, 'ATTENZIONE: CrypTax e Cristian Livella non si assumono nessuna responsabilita riguardo la correttezza');
        $this->writeXY(10, 280, 'e la completezza dei dati riportati in questo modulo. Il software offre un aiuto nel calcolo');
        $this->writeXY(10, 284, 'delle plusvalenze e nella compilazione dei modelli, ma e\' responsabilita\' del contribuente di verificarne');
        $this->writeXY(10, 288, 'la correttazza e la compatibilita\' con la propria situazione finanziaria complessiva.');
    }
}
