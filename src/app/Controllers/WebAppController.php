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
    const ACTION_SET_SETTINGS = 'set_settings';

    public static function run() {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        switch ($action) {
            case self::ACTION_REPORT:
                self::printReport();
            case self::ACTION_PDF_MODELLO_REDDITI:
                self::printModelloRedditi();
            case self::ACTION_PDF_MODELLO_F24:
                self::printModelloF24();
            case self::ACTION_UPLOAD:
                self::upload();
                break;
            case self::ACTION_SET_SETTINGS:
                self::setSettings();
            default:
                self::printReport();
                // throw new ActionInvalidException($action);
        }
    }

    public static function printReport() {
        $year = $_GET['year'] ?? DateUtils::getCurrentYear();

        $settings = self::getSelectedReportSettings();
        $exchangeSettings = $settings['exchanges'] ?? [];

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings);

        echo $reportWrapper->getReport($year);
    }

    public static function printModelloRedditi() {
        $year = $_GET['year'] ?? DateUtils::getCurrentYear();

        $settings = self::getSelectedReportSettings();
        $compensateCapitalLosses = $settings['compensate_losses'] ?? true;
        $exchangeSettings = $settings['exchanges'] ?? [];

        $reportWrapper = new ReportWrapper(self::getSelectedReportContent(), $exchangeSettings);

        echo $reportWrapper->getModelloRedditi($year, $compensateCapitalLosses);
    }

    public static function printModelloF24() {
        $year = $_GET['year'] ?? DateUtils::getCurrentYear();

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

        setcookie('KEY-' . $reportId, $key, self::getCookieOptions());

        header('Content-type: application/json');
        echo json_encode(['report_id' => $reportId] + $reportWrapper->getSummary(true));
    }

    public static function setSettings() {
        $reportId = $_POST['report_id'];

        if (strlen($reportId) !== 32) {
            throw new NotFoundException('report');
        }

        $settings = self::getSelectedReportSettings();

        if (isset($_POST['exchanges'])) {
            $settings['exchanges'] = json_decode($_POST['exchanges']);
        }

        if (isset($_POST['compensate_losses'])) {
            $settings['exchanges'] = filter_var($_POST['compensate_losses'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        setcookie('SETTINGS-' . $reportId, base64_encode(json_encode($settings)), self::getCookieOptions());
    }

    private static function getSelectedReportContent() {
        $reportId = $_GET['id'];
        $filePath = dirname(__FILE__) . '/../../tmp/' . $reportId;

        if (strlen($reportId) !== 32 || !file_exists($filePath)) {
            throw new NotFoundException('report');
        }

        $fileContent = AesUtils::decrypt(file_get_contents($filePath), $_COOKIE['KEY-' . $reportId] ?? '');
        return $fileContent;
    }

    private static function getSelectedReportSettings() {
        $reportId = $_GET['id'] ?? $_POST['id'] ?? null;

        $filePath = dirname(__FILE__) . '/../../tmp/' . $reportId;

        if (strlen($reportId) !== 32 || !file_exists($filePath)) {
            throw new NotFoundException('report');
        }

        return json_decode(base64_decode($_COOKIE['SETTINGS-' . $reportId] ?? ''));
    }

    private static function getCookieOptions() {
        return [
            'expires' => time() + 60 * 60 * 12,
            'secure' => true,
            'httponly' => true
        ];
    }
}
