<?php

namespace CrypTax\Models;

use CrypTax\Helpers\VersionHelper;

use setasign\Fpdi\Fpdi;

class ModelloF24 extends ModelloRedditiSection
{
    private $pdf = null;
    private $fiscalYear;
    private $taxes;

    public function __construct($fiscalYear, $taxes) {
        $this->pdf = new Fpdi();
        $this->pdf->SetAutoPageBreak(true, 0);
        $this->pdf->SetRightMargin(0);

        $this->fiscalYear = $fiscalYear;
        $this->taxes = $taxes;

        $this->pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/F24.pdf');
        $templateId = $this->pdf->importPage(1);
        $this->pdf->addPage();
        $this->pdf->useTemplate($templateId);

        self::addHeaderFooter($this->pdf, $fiscalYear);

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('Courier', 'B', 10);

        $total = 0;

        foreach ($taxes AS $index => $tax) {
            self::writeRTL($this->pdf, 69, 87.3 + $index * 4.3, $tax['code']);
            self::writeRTL($this->pdf, 89, 87.3 + $index * 4.3, '0101');
            self::writeRTL($this->pdf, 107, 87.3 + $index * 4.3, $fiscalYear);
            self::writeRTL($this->pdf, 140.2, 87.3 + $index * 4.3, number_format($tax['amount'], 2, ' ', ''));

            $total += $tax['amount'];
        }

        self::writeRTL($this->pdf, 201, 112.5, number_format($total, 2, ' ', ''));
        self::writeRTL($this->pdf, 201, 252.5, number_format($total, 2, ' ', ''));

    }

    public function getPdf() {
        return $this->pdf->Output();
    }


}
