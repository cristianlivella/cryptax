<?php

namespace CrypTax\Models;

use CrypTax\Models\PdfDocument;
use CrypTax\Utils\VersionUtils;

class ModelloF24
{
    private $pdf = null;

    public function __construct($fiscalYear, $taxes) {
        $totalTaxes = 0;

        $this->pdf = new PdfDocument();

        $this->pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/F24.pdf');
        $templateId = $this->pdf->importPage(1);
        $this->pdf->addPage();
        $this->pdf->useTemplate($templateId);

        $this->pdf->addHeaderFooter($fiscalYear, false);

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Courier', 'B', 10);
        $this->pdf->writeXY(80, 10, 'Scadenza del 30-06-' . ($fiscalYear + 1));

        $totalTaxes = 0;

        foreach ($taxes AS $index => $tax) {
            $this->pdf->writeRTL(69, 87.3 + $index * 4.3, $tax['code']);
            $this->pdf->writeRTL(89, 87.3 + $index * 4.3, '0101');
            $this->pdf->writeRTL(107, 87.3 + $index * 4.3, $fiscalYear);
            $this->pdf->writeRTL(140.2, 87.3 + $index * 4.3, number_format($tax['amount'], 2, ' ', ''));

            $totalTaxes += $tax['amount'];
        }

        $this->pdf->writeRTL(201, 112.5, number_format($totalTaxes, 2, ' ', ''));
        $this->pdf->writeRTL(201, 252.5, number_format($totalTaxes, 2, ' ', ''));
    }

    public function getPdf() {
        return $this->pdf->Output();
    }
}
