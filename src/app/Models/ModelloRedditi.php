<?php

namespace CrypTax\Models;

use CrypTax\Models\PdfDocument;
use CrypTax\Utils\VersionUtils;

class ModelloRedditi
{
    private $pdf = null;
    private $fiscalYear;

    public function __construct($info) {
        $this->pdf = new PdfDocument();

        $this->fiscalYear = $info['fiscal_year'];

        if ($info['sections_required']['rl']) {
            ModelloRedditiRl::fill($this->pdf, $info, $this->fiscalYear);
        }

        if ($info['sections_required']['rw']) {
            ModelloRedditiRw::fill($this->pdf, $info, $this->fiscalYear);
        }

        if ($info['sections_required']['rt']) {
            ModelloRedditiRt::fill($this->pdf, $info, $this->fiscalYear);
        }

        if ($info['sections_required']['rm']) {
            ModelloRedditiRm::fill($this->pdf, $info, $this->fiscalYear);
        }
    }

    public function getPdf() {
        return $this->pdf->Output();
    }
}
