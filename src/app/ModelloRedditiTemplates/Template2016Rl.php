<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2016Rl extends Template
{
    const FISCAL_YEAR = 2016;
    const FIRST_PAGE = 2;
    const LAST_PAGE = 2;

    public function __construct() {
        $this->setField('tipo_reddito', 127, 57.4);
        $this->setField('altri_redditi_di_capitale', 162.8, 57.4, true);
        $this->setField('altri_redditi_di_capitale_ritenute', 193, 57.4, true);
        $this->setField('totale', 162.8, 66, true);
        $this->setField('totale_ritenute', 193, 66, true);
    }
}
