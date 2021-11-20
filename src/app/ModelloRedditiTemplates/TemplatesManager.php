<?php

namespace CrypTax\ModelloRedditiTemplates;

use CrypTax\Exceptions\InvalidYearException;

class TemplatesManager
{
    const TYPE_RL = 'rl';
    const TYPE_RM = 'rm';
    const TYPE_RT = 'rt';
    const TYPE_RW = 'rw';

    const TEMPLATES_RL = [
        2016 => Template2016Rl::class,
        2017 => Template2017Rl::class,
        2018 => Template2018Rl::class,
        2019 => Template2019Rl::class,
        2020 => Template2020Rl::class
    ];

    const TEMPLATES_RM = [
        2016 => Template2016Rm::class,
        2017 => Template2017Rm::class,
        2018 => Template2018Rm::class,
        2019 => Template2019Rm::class,
        2020 => Template2020Rm::class
    ];

    const TEMPLATES_RT = [
        2016 => Template2016Rt::class,
        2017 => Template2017Rt::class,
        2018 => Template2018Rt::class,
        2019 => Template2019Rt::class,
        2020 => Template2020Rt::class
    ];

    const TEMPLATES_RW = [
        2016 => Template2016Rw::class,
        2017 => Template2017Rw::class,
        2018 => Template2018Rw::class,
        2019 => Template2019Rw::class,
        2020 => Template2020Rw::class,
    ];

    public static function isTemplateAvailable($year) {
        return isset(self::TEMPLATES_RL[$year]) && isset(self::TEMPLATES_RM[$year]) && isset(self::TEMPLATES_RT[$year]) && isset(self::TEMPLATES_RW[$year]);
    }

    public static function getTemplate($year, $type) {
        if ($type === self::TYPE_RL) {
            return self::getTemplateFromArray(self::TEMPLATES_RL, $year);
        } elseif ($type === self::TYPE_RM) {
            return self::getTemplateFromArray(self::TEMPLATES_RM, $year);
        } elseif ($type === self::TYPE_RT) {
            return self::getTemplateFromArray(self::TEMPLATES_RT, $year);
        } elseif ($type === self::TYPE_RW) {
            return self::getTemplateFromArray(self::TEMPLATES_RW, $year);
        }
    }

    private static function getTemplateFromArray($array, $year) {
        if (isset($array[$year])) {
            $class = $array[$year];
            $template = new $class();

            return $template;
        } elseif (isset($_GET['force']) && count($array) > 0) {
            return self::getTemplateFromArray($array, array_keys($array)[count($array) - 1]);
        } else {
            throw new InvalidYearException($year);
        }
    }
}
