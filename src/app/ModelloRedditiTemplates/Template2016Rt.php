<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2016Rt extends Template
{
    const FISCAL_YEAR = 2016;
    const FIRST_PAGE = 5;
    const LAST_PAGE = 5;

    public function __construct() {
        // RT21 - Totale dei corrispettivi
        $this->setField('totale_corrispettivi', 192, 91.2, true);

        // RT22 - Totale dei costi o dei valori di acquisto
        $this->setField('totale_costi_acquisto', 192, 95.4, true);

        // RT23.1 - Minusvalenze
        $this->setField('minusvalenze', 118.4, 99.6, true);

        // RT23.3 - Plusvalenze
        $this->setField('plusvalenze', 192, 99.6, true);

        // RT24.1 - Eccedenza minusvalenze anni precedenti
        $this->setField('minusvalenze_anni_precedenti', 129, 103.8, true);

        // RT24.4 - Eccedenza minusvalenze
        $this->setField('eccedenze_minusvalenze', 192, 103.8, true);

        // RT26 - Differenza
        $this->setField('differenza_plus_minus', 192, 112.2, true);

        // RT27 - Imposta sostitutiva
        $this->setField('imposta_sostitutiva', 192, 116.4, true);

        // RT29 - Imposta sostitutiva dovuta
        $this->setField('imposta_sostitutiva_dovuta', 192, 124.8, true);

        // RT93 - Minusvalenze non compensate nell'anno
        for ($i = 4; $i >= 0; $i--) {
            if (static::FISCAL_YEAR - $i < 2016) {
                // first report generated is for fiscal year 2016, skip previous years
                continue;
            }
            $this->setField('minusvalenze_anno_' . $i, 192 - $i * 27.8, 231, true);
        }
    }
}
