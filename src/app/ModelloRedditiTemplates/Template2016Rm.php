<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2016Rm extends Template
{
    const FISCAL_YEAR = 2016;
    const FIRST_PAGE = 3;
    const LAST_PAGE = 4;

    public function __construct() {
        $this->setField('tipo_reddito', 46, 121, false);
        $this->setField('ammontare_reddito', 111.5, 121, true);
        $this->setField('aliquota', 120, 121);
        $this->setField('imposta_dovuta', 164.5, 121, true);
    }
}
