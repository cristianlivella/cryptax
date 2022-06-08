<?php

namespace CrypTax\Controllers;

use CrypTax\Exceptions\NotFoundException;
use CrypTax\Exceptions\FileTooBigException;
use CrypTax\Exceptions\InvalidFileException;
use CrypTax\Exceptions\ActionInvalidException;

use CrypTax\Models\ReportWrapper;

use CrypTax\Utils\AesUtils;
use CrypTax\Utils\DateUtils;

class WebAppController
{
    const ACTION_REPORT = 'report';
    const ACTION_PDF_MODELLO_REDDITI = 'pdf_modello_redditi';
    const ACTION_PDF_MODELLO_F24 = 'pdf_modello_f24';
    const ACTION_UPLOAD = 'upload';
    const ACTION_GET_INFO = 'get_info';
    const ACTION_SET_SETTINGS = 'set_settings';

    public static function run() {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        switch ($action) {
            case self::ACTION_REPORT:
                self::printReport();
                break;
            case self::ACTION_PDF_MODELLO_REDDITI:
                self::printModelloRedditi();
                break;
            case self::ACTION_PDF_MODELLO_F24:
                self::printModelloF24();
                break;
            case self::ACTION_UPLOAD:
                self::upload();
                break;
            case self::ACTION_GET_INFO:
                self::getInfo();
                break;
            case self::ACTION_SET_SETTINGS:
                self::setSettings();
                break;
            default:
                self::printReport();
                // throw new ActionInvalidException($action);
        }
    }

    public static function printReport() {
        $year = self::getSelectedYear();

        $settings = self::getSelectedReportSettings();
        $exchangeSettings = $settings['exchanges'] ?? [];
        $considerEarningsAndExpensesAsInvestment = $settings['consider_earnings_and_expenses_as_investment'] ?? true;

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings, $considerEarningsAndExpensesAsInvestment);

        echo $reportWrapper->getReport($year);
    }

    public static function printModelloRedditi() {
        $year = self::getSelectedYear();

        $settings = self::getSelectedReportSettings();
        $compensateCapitalLosses = $settings['compensate_losses'] ?? true;
        $exchangeSettings = $settings['exchanges'] ?? [];
        $finalValueMethod = $settings['rw_final_value_method'] ?? 'average_value';
        $considerEarningsAndExpensesAsInvestment = $settings['consider_earnings_and_expenses_as_investment'] ?? true;

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings, $considerEarningsAndExpensesAsInvestment);

        echo $reportWrapper->getModelloRedditi($year, $compensateCapitalLosses, $finalValueMethod);
    }

    public static function printModelloF24() {
        $year = self::getSelectedYear();

        $settings = self::getSelectedReportSettings();
        $compensateCapitalLosses = $settings['compensate_losses'] ?? true;
        $exchangeSettings = $settings['exchanges'] ?? [];

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings);

        echo $reportWrapper->getModelloF24($year, $compensateCapitalLosses);
    }

    public static function upload() {
        $file = array_values($_FILES)[0] ?? null;

        if ($file === null) {
            throw new InvalidFileException();
        }

        if ($file['size'] > 25 * 1024 * 1024) {
            throw new FileTooBigException();
        }

        $reportId = bin2hex(random_bytes(16));
        $key = AesUtils::generateKey();
        $filePath = dirname(__FILE__) . '/../../tmp/' . $reportId;

        file_put_contents($filePath, AesUtils::encrypt(file_get_contents($file['tmp_name']), $key));
        unlink($file['tmp_name']);

        $reportWrapper = new ReportWrapper(AesUtils::decrypt(file_get_contents($filePath), $key));

        self::setCookie('KEY-' . $reportId, $key);

        header('Content-type: application/json');
        echo json_encode(['report_id' => $reportId] + $reportWrapper->getSummary(true));
    }

    public static function getInfo() {
        $reportId = self::getSelectedReportId();

        $exchangeSettings = $settings['exchanges'] ?? [];
        $considerEarningsAndExpensesAsInvestment = $settings['consider_earnings_and_expenses_as_investment'] ?? true;

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings, $considerEarningsAndExpensesAsInvestment);

        header('Content-type: application/json');
        echo json_encode(['report_id' => $reportId] + $reportWrapper->getSummary(true));
    }

    public static function setSettings() {
        $reportId = self::getSelectedReportId();
        $settings = self::getSelectedReportSettings();

        if ($settings === null) {
            $settings = [];
        }

        if (isset($_POST['exchanges'])) {
            $settings['exchanges'] = json_decode($_POST['exchanges'], true);
        }

        if (isset($_POST['compensate_losses'])) {
            $settings['compensate_losses'] = filter_var($_POST['compensate_losses'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_POST['rw_final_value_method'])) {
            $settings['rw_final_value_method'] = $_POST['rw_final_value_method'];
        }

        if (isset($_POST['consider_earnings_and_expenses_as_investment'])) {
            $settings['consider_earnings_and_expenses_as_investment'] = $_POST['consider_earnings_and_expenses_as_investment'];
        }

        self::setCookie('SETTINGS-' . $reportId, base64_encode(json_encode($settings)));
    }

    private static function getSelectedYear() {
        return isset($_GET['year']) ? intval($_GET['year']) : DateUtils::getCurrentYear();
    }

    private static function getSelectedReportContent() {
        $reportId = self::getSelectedReportId();
        $filePath = dirname(__FILE__) . '/../../tmp/' . basename($reportId);

        if (strlen($reportId) !== 32 || !file_exists($filePath)) {
            throw new NotFoundException('report');
        }

        $fileContent = AesUtils::decrypt(file_get_contents($filePath), $_COOKIE['KEY-' . $reportId] ?? '');
        return $fileContent;
    }

    private static function getSelectedReportSettings() {
        $reportId = self::getSelectedReportId();

        $filePath = dirname(__FILE__) . '/../../tmp/' . basename($reportId);

        if (strlen($reportId) !== 32 || !file_exists($filePath)) {
            throw new NotFoundException('report');
        }

        return json_decode(base64_decode($_COOKIE['SETTINGS-' . $reportId] ?? ''), true);
    }

    private static function getSelectedReportId() {
        return $_GET['id'] ?? $_POST['id'] ?? null;
    }

    private static function setCookie($name, $value) {
        setcookie($name, $value, time() + 60 * 60 * 12, '', '', true, true);
    }
}
