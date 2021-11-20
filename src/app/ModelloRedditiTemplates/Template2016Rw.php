<?php

namespace CrypTax\ModelloRedditiTemplates;

class Template2016Rw extends Template
{
    const FISCAL_YEAR = 2016;
    const FIRST_PAGE = 7;
    const LAST_PAGE = 7;

    public function __construct() {
        $this->setField('codice_titolo_possesso', 46, 57.8);
        $this->setField('titolare_effettivo', 61, 57.8);
        $this->setField('codice_individuazione_bene', 76, 57.8);
        $this->setField('quota_possesso', 106, 57.8);
        $this->setField('criterio_determinazione_valore', 121, 57.8);
        $this->setField('valore_iniziale', 159, 57.8, true);
        $this->setField('valore_finale', 192.3, 57.8, true);
        $this->setField('quadri_aggiuntivi', 164, 82.6);
        $this->setField('solo_monitoraggio', 191, 82.6);
    }
}
