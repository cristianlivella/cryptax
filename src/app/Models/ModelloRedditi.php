<?php

namespace CrypTax\Models;

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
