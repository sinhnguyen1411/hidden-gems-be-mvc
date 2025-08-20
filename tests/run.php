<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/TestCase.php';

$testFiles = glob(__DIR__ . '/*Test.php');
$failures = [];

foreach ($testFiles as $file) {
    require $file;
    $class = pathinfo($file, PATHINFO_FILENAME);
    $test = new $class();
    foreach (get_class_methods($test) as $method) {
        if (str_starts_with($method, 'test')) {
            try {
                $test->runTest($method);
                fwrite(STDOUT, '.');
            } catch (\Throwable $e) {
                $failures[] = "$class::$method - {$e->getMessage()}";
                fwrite(STDOUT, 'F');
            }
        }
    }
}

fwrite(STDOUT, PHP_EOL);
if ($failures) {
    foreach ($failures as $fail) {
        fwrite(STDOUT, $fail . PHP_EOL);
    }
    exit(1);
}
