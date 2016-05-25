<?php declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

try {
    \Schnitzler\Bootstrap::boot(__DIR__);
} catch (\Exception $e) {
    die('Error #' . $e->getCode() . ': ' . $e->getMessage() . PHP_EOL);
}

$application = new Symfony\Component\Console\Application();
$application->add(new Schnitzler\Command\ImportCommand());
$application->add(new Schnitzler\Command\AnalyzeCommand());
$application->run();
