<?php

namespace CrypTax\PdfRender;

use CrypTax\Helpers\VersionHelper;

use setasign\Fpdi\Fpdi;

class ModelloRedditi
{
    private $pdf = null;
    private $fiscalYear;

    public function __construct($info) {
        $this->pdf = new Fpdi();
        $this->pdf->SetAutoPageBreak(true, 0);

        $this->fiscalYear = $info['fiscal_year'];

        if ($info['sections_required']['rw']) {
            $this->fillRW($info['rw']);
        }
    }

    public function getPdf() {
        return $this->pdf->Output();
    }

    private function fillRW($info) {
        $countPages = $this->pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-RW.pdf');

        for ($page = 1; $page <= $countPages; $page++) {
            $templateId = $this->pdf->importPage($page);

            $this->pdf->addPage();
            $this->pdf->useTemplate($templateId);

            $this->addHeaderFooter();

            if ($page === 1) {
                $this->pdf->SetTextColor(0, 0, 0);
                $this->pdf->SetFont('Courier', 'B', 10);

                // 1 - Codice titolo possesso
                $this->pdf->SetXY(46, 57.8);
                $this->pdf->Write(0, '1');

                // 2 - Vedere istruzioni
                $this->pdf->SetXY(61, 57.8);
                $this->pdf->Write(0, '2');

                // 3 - Codice individuazione bene
                $this->pdf->SetXY(76, 57.8);
                $this->pdf->Write(0, '14');

                // 4 - Quota di possesso
                $this->pdf->SetXY(106, 57.8);
                $this->pdf->Write(0, '100');

                // 5 - Criterio determinazione valore
                $this->pdf->SetXY(121, 57.8);
                $this->pdf->Write(0, '1');

                // 6 - Valore iniziale
                $this->pdf->SetXY(159 - strlen(round($info['initial_value'])) * 2, 57.8);
                $this->pdf->Write(0, round($info['initial_value'], 0));

                // 7 - Valore finale
                $this->pdf->SetXY(192.3 - strlen(round($info['final_value'])) * 2, 57.8);
                $this->pdf->Write(0, round($info['final_value'], 0));

                // 18 - Vedere istruzioni
                $this->pdf->SetXY(164, 82.6);
                $this->pdf->Write(0, '5');

                // 20 - Solo monitoraggio
                $this->pdf->SetXY(191, 82.6);
                $this->pdf->Write(0, 'X');

            }
        }
    }

    private function addHeaderFooter() {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->SetXY(150, 7);
        $this->pdf->Write(0, 'PERIODO D\'IMPOSTA ' . $this->fiscalYear);

        $this->pdf->SetTextColor(0, 66, 112);
        $this->pdf->SetFont('Helvetica', 'B', 14);
        $this->pdf->SetXY(42, 32);
        $this->pdf->Write(0, ($this->fiscalYear + 1));

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Courier', '', 10);
        $this->pdf->SetXY(160, 13);
        $this->pdf->Write(0, 'Facsimile CrypTax');

        $this->pdf->SetFont('Courier', '', 9);
        $this->pdf->SetXY(10, 264);
        $this->pdf->Write(0, 'Generato da CrypTax - ' . VersionHelper::getVersion() . ' - github.com/cristianlivella/cryptax');

        $this->pdf->SetFont('Courier', '', 8);
        $this->pdf->SetXY(10, 268);
        $this->pdf->Write(0, 'Modello generato in data ' . date('d/m/Y') . ' alle ore ' . date('H:i:s') . '.');

        $this->pdf->SetXY(10, 276);
        $this->pdf->Write(0, 'ATTENZIONE: CrypTax e Cristian Livella non si assumono nessuna responsabilita riguardo la correttezza');

        $this->pdf->SetXY(10, 280);
        $this->pdf->Write(0, 'e la completezza dei dati riportati in questo modulo. Il software offre un aiuto nel calcolo');

        $this->pdf->SetXY(10, 284);
        $this->pdf->Write(0, 'delle plusvalenze e nella compilazione dei modelli, ma e\' responsabilita\' del contribuente di verificarne');

        $this->pdf->SetXY(10, 288);
        $this->pdf->Write(0, 'la correttazza e la compatibilita\' con la propria situazione finanziaria complessiva.');
    }
}
