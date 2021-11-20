<?php

namespace CrypTax\ModelloRedditiTemplates;

abstract class Template
{
    private $config = [];
    private $writeQueue = [];

    protected function setField($name, $posX, $posY, $rtl = false, $page = 1) {
        $this->config[$name] = [
            'x' => $posX,
            'y' => $posY,
            'page' => $page,
            'rtl' => $rtl
        ];
    }

    protected function removeField($name) {
        unset($this->config[$name]);
    }

    public function setValue($name, $value) {
        if (!isset($this->config[$name])) {
            return;
        }

        $config = $this->config[$name];

        $this->writeQueue[] = [
            'x' => $config['x'],
            'y' => $config['y'],
            'page' => $config['page'],
            'rtl' => $config['rtl'],
            'value' => $value
        ];
    }

    public function writeOnPdf($pdf) {
        $pdf->setSourceFile(dirname(__FILE__) . '/../../resources/pdf/PF-' . static::FISCAL_YEAR . '.pdf');

        $currentPage = 0;

        // TODO: order writeQueue by page number (however, currently only the first page of each section is filled)

        foreach ($this->writeQueue AS $item) {
            if ($item['page'] !== $currentPage) {
                $currentPage = $item['page'];

                $templateId = $pdf->importPage($item['page'] + static::FIRST_PAGE - 1);
                $pdf->addPage();
                $pdf->useTemplate($templateId);
                $pdf->addHeaderFooter(static::FISCAL_YEAR, $currentPage === 1);

                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Courier', 'B', 10);
            }

            if ($item['rtl']) {
                $pdf->writeRTL($item['x'], $item['y'], $item['value']);
            } else {
                $pdf->writeXY($item['x'], $item['y'], $item['value']);
            }
        }

        while ($currentPage !== (static::LAST_PAGE - static::FIRST_PAGE + 1)) {
            $currentPage++;
            $templateId = $pdf->importPage($currentPage + static::FIRST_PAGE - 1);
            $pdf->addPage();
            $pdf->useTemplate($templateId);
            $pdf->addHeaderFooter(static::FISCAL_YEAR, $currentPage === 1);
        }
    }
}
