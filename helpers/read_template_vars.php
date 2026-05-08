<?php
require_once __DIR__ . '/../vendor/autoload.php';
$tp = new \PhpOffice\PhpWord\TemplateProcessor(__DIR__ . '/../templates/Izin_telat_dan_pulangcepat.docx');
print_r($tp->getVariables());
