<?php

namespace CrypTax\Models;

use CrypTax\ModelloRedditiTemplates\TemplatesManager;

abstract class ModelloRedditiRl
{
    public static function fill($pdf, $info, $fiscalYear) {
        $template = TemplatesManager::getTemplate($fiscalYear, TemplatesManager::TYPE_RL);

        $template->setValue('tipo_reddito', '1');
        $template->setValue('altri_redditi_di_capitale', $info['rl']['interests']);
        $template->setValue('altri_redditi_di_capitale_ritenute', '0');
        $template->setValue('totale', $info['rl']['interests']);
        $template->setValue('totale_ritenute', '0');

        $template->writeOnPdf($pdf);
    }
}
