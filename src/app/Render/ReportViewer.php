<?php

namespace CrypTax\Render;

class ReportViewer
{
    private $report;

    public function __construct($report) {
        $this->report = $report;
    }

    public function render() {
        $loader = new \Twig\Loader\FilesystemLoader('resources/views/');
        $twig = new \Twig\Environment($loader);

        //var_dump($this->report->getInfoForRender());

        return $twig->render('report.html', [
            'header' => HEADER
        ] + $this->report->getInfoForRender());
    }
}
