<?php

namespace CrypTax\Utils;

class NumberUtils
{
    /**
     * Round and format all numbers in in array, recursively.
     *
     * @param array $array
     * @param integer $digits
     * @param  boolean $roundOnly [description]
     * @param  boolean $pass      [description]
     * @return array
     */
    public static function recursiveFormatNumbers($array, $digits = 2, $roundOnly = false, $pass = false) {
        if ($pass) {
            return $array;
        }

        foreach ($array AS $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::recursiveFormatNumbers($value, $digits, $roundOnly);
            } else {
                $array[$key] = $roundOnly ? round($value, $digits) : number_format($value, $digits, ',', '.');
            }
        }
        return $array;
    }
}
