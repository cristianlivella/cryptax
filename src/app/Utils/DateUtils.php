<?php

namespace CrypTax\Utils;

use DateTime;

class DateUtils {
    private static $dateFromStringCache = [];

    /**
     * Get date in Y-m-d format from string.
     *
     * @param string $string
     * @return string
     */
    public static function getDateFromString($string) {
        if (!isset(self::$dateFromStringCache[$string])) {
            self::$dateFromStringCache[$string] = date('Y-m-d', strtotime($string));
        }

        return self::$dateFromStringCache[$string];
    }

    /**
     * Get today date in Y-m-d format.
     *
     * @return string
     */
    public static function getToday() {
        return self::getDateFromString('today');
    }

    /**
     * Get date in Y-m-d format from a date in d/m/Y format.
     *
     * @param string $string
     * @return string
     */
    public static function getDateFromItFormat($string) {
        return self::getDateFromString(str_replace('/', '-', $string));
    }

    /**
     * Get the first day of a specific year, in Y-m-d format.
     *
     * @param integer $year
     * @return string
     */
    public static function getFirstDayOfYear($year) {
        return date('Y-m-d', mktime(0, 0, 0, 1, 1, $year));
    }

    /**
     * Get the last day of a specific year, in Y-m-d format.
     *
     * @param integer $year
     * @return string
     */
    public static function getLastDayOfYear($year) {
        return date('Y-m-d', mktime(0, 0, 0, 12, 31, $year));
    }

    /**
     * Extract the year from a Y-m-d date.
     *
     * @param string $date
     * @return integer
     */
    public static function getYearFromDate($date) {
        return intval(date('Y', strtotime($date)));
    }

    /**
     * Get the index of the day of year of a specific date.
     *
     * @param string $date
     * @return integer
     */
    public static function getDayOfYear($date) {
        return intval(date('z', strtotime($date)));
    }

    /**
     * Get a date in Y-m-d format from a day index and a year.
     *
     * @param integer $day
     * @param  integer $year
     * @return string
     */
    public static function getDateFromDayOfYear($day, $year) {
        return date('Y-m-d', DateTime::createFromFormat('Y z', $year . ' ' . $day)->getTimestamp());
    }

    /**
     * Return the number of days in a specific year.
     *
     * @param integer $year
     * @return integer
     */
    public static function getNumberOfDaysInYear($year) {
        return intval(date('z', mktime(0, 0, 0, 12, 31, $year))) + 1;
    }

    /**
     * Deprecated. TODO: to remove.
     */
    public static function old_getNumerOfDaysInYear($year) {
        return intval(date('z', mktime(0, 0, 0, 12, 31, $year)));
    }

    /**
     * Get the current year.
     *
     * @return integer
     */
    public static function getCurrentYear() {
        return intval(date('Y'));
    }

    /**
     * Get the day of the week (0 sunday - 6 saturday) from a day of year (0-365)
     *
     * @param integer $day
     * @param integer $year
     * @return integer
     */
    public static function getDayOfWeek($day, $year) {
        // get the day of the week (0 sunday - 6 saturday) based on day of year (0-365)
        return intval(date('w', DateTime::createFromFormat('Y z', $year . ' ' . $day)->getTimestamp()));
    }

     /**
      * Get the list of day in a specific year, in a specific format.
      *
      * @param integer $year
      * @param integer $format
      * @return string[]
      */
    public static function getListDaysInYear($year, $format = 'd/m') {
        $daysList = [];
        $daysInYear = self::getNumberOfDaysInYear($year);

        for ($i = 0; $i < $daysInYear; $i++) {
            $daysList[] = date('d/m', DateTime::createFromFormat('Y z', $year . ' ' . $i)->getTimestamp());
        }

        return $daysList;
    }

    /**
     * Get the holidays in a specific year.
     *
     * @param integer $year
     * @return integer[]
     */
    public static function getHolidays($year) {
        $holidays = [
            [1, 1],     // Capodanno
            [6, 1],     // Epifania
            [5, 4],     // Anniversario della Liberazione
            [1, 5],     // Festa del Lavoro
            [2, 6],     // Festa della Repubblica
            [15, 8],    // Assunzione / Ferragosto
            [1, 11],    // Tutti i santi
            [8, 12],    // Immacolata concezione
            [25, 12],   // Natale
            [26, 12],   // Santo Stefano
        ];

        $easter = easter_date($year);
        $holidays[] = explode('-', date('d-m', $easter));                   // Pasqua
        $holidays[] = explode('-', date('d-m', $easter + (60 * 60 * 24)));  // Pasquetta

        $holidayDays = [];

        foreach ($holidays AS $holiday) {
            $holidayDays[] = intval(date('z', mktime(0, 0, 0, $holiday[1], $holiday[0], $year)));
        }

        return $holidayDays;
    }
}
