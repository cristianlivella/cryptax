<?php

namespace CrypTax\Models;

use CrypTax\ModelloRedditiTemplates\TemplatesManager;

abstract class ModelloRedditiRw
{
    public static function fill($pdf, $info, $fiscalYear) {
        $template = TemplatesManager::getTemplate($fiscalYear, TemplatesManager::TYPE_RW);

        $template->setValue('codice_titolo_possesso', '1');
        $template->setValue('titolare_effettivo', '2');
        $template->setValue('codice_individuazione_bene', '14');
        $template->setValue('quota_possesso', '100');
        $template->setValue('criterio_determinazione_valore', '1');
        $template->setValue('valore_iniziale', round($info['rw']['initial_value']));
        $template->setValue('valore_finale', round($info['rw']['final_value']));

        $requiredSections = 0;
        foreach ($info['sections_required'] AS $section => $required) {
            if ($section !== 'rw' && $required) {
                $requiredSections++;
            }
        }

        if ($requiredSections > 1) {
            $template->setValue('quadri_aggiuntivi', '4');
        } elseif ($info['sections_required']['rl']) {
            $template->setValue('quadri_aggiuntivi', '1');
        } elseif ($info['sections_required']['rm']) {
            $template->setValue('quadri_aggiuntivi', '2');
        } elseif ($info['sections_required']['rt']) {
            $template->setValue('quadri_aggiuntivi', '3');
        } else {
            $template->setValue('quadri_aggiuntivi', '5');
        }

        $template->setValue('solo_monitoraggio', 'X');

        $template->writeOnPdf($pdf);
    }
}
