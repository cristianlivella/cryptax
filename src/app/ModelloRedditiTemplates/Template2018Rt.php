<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2018Rt extends Template2017Rt
{
    const FISCAL_YEAR = 2018;

    public function __construct() {
        parent::__construct();

        // RT93 - Minusvalenze non compensate nell'anno
        for ($i = 4; $i >= 0; $i--) {
            if (static::FISCAL_YEAR - $i < 2016) {
                // first report generated is for fiscal year 2016, skip previous years
                continue;
            }
            $this->setField('minusvalenze_anno_' . $i, 192 - $i * 27.8, 218, true);
        }
    }
}
