<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2019Rt extends Template2018Rt
{
    const FISCAL_YEAR = 2019;
    const FIRST_PAGE = 7;
    const LAST_PAGE = 7;

    public function __construct() {
        parent::__construct();

        // RT24.1 - Eccedenza minusvalenze anni precedenti
        $this->setField('minusvalenze_anni_precedenti', 98.4, 103.8, true);
    }
}
