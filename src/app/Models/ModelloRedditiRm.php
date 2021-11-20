<?php

namespace CrypTax\Models;

use CrypTax\ModelloRedditiTemplates\TemplatesManager;

abstract class ModelloRedditiRm
{
    public static function fill($pdf, $info, $fiscalYear) {
        $template = TemplatesManager::getTemplate($fiscalYear, TemplatesManager::TYPE_RM);

        $template->setValue('tipo_reddito', 'G');
        $template->setValue('ammontare_reddito', $info['rm']['interests']);
        $template->setValue('aliquota', $info['rm']['tax_rate']);
        $template->setValue('imposta_dovuta', $info['rm']['tax']);

        $template->writeOnPdf($pdf);
    }
}
