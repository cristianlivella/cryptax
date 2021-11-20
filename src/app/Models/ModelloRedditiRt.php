<?php

namespace CrypTax\Models;

use CrypTax\ModelloRedditiTemplates\TemplatesManager;

abstract class ModelloRedditiRt
{
    public static function fill($pdf, $info, $fiscalYear) {
        $template = TemplatesManager::getTemplate($fiscalYear, TemplatesManager::TYPE_RT);

        $template->setValue('totale_corrispettivi', $info['rt']['total_incomes']);
        $template->setValue('totale_costi_acquisto', $info['rt']['total_costs']);
        $template->setValue('minusvalenze', $info['rt']['capital_losses']);
        $template->setValue('plusvalenze', $info['rt']['capital_gains']);

        if ($info['rt']['compensate_capital_losses']) {
            $template->setValue('minusvalenze_anni_precedenti', $info['rt']['capital_losses_previous_years']);
            $template->setValue('eccedenze_minusvalenze', $info['rt']['capital_losses_previous_years']);
            $template->setValue('differenza_plus_minus', $info['rt']['capital_gains_compensated']);
            $template->setValue('imposta_sostitutiva', $info['rt']['capital_gains_compensated_tax']);
            $template->setValue('imposta_sostitutiva_dovuta', $info['rt']['capital_gains_compensated_tax']);

            for ($i = 4; $i >= 0; $i--) {
                $template->setValue('minusvalenze_anno_' . $i, $info['rt']['remaining_capital_losses'][$fiscalYear - $i] ?? 0);
            }
        } else {
            $template->setValue('differenza_plus_minus', $info['rt']['capital_gains']);
            $template->setValue('imposta_sostitutiva', $info['rt']['capital_gains_tax']);
            $template->setValue('imposta_sostitutiva_dovuta', $info['rt']['capital_gains_tax']);
        }

        $template->writeOnPdf($pdf);
    }
}
