<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2019Rm extends Template2018Rm
{
    const FISCAL_YEAR = 2019;
    const FIRST_PAGE = 4;
    const LAST_PAGE = 6;

    public function __construct() {
        parent::__construct();

        $this->setField('ammontare_reddito', 96, 121, true);
        $this->setField('aliquota', 108, 121);
        $this->setField('imposta_dovuta', 159.5, 121, true);
    }
}
