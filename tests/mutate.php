<?php

declare(strict_types=1);

use Pest\Kernel;
use Pest\Mutate\Support\StreamWrapper;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

if (filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL)) {
    throw new RuntimeException('Mutation testing requires opcache.enable_cli=0.');
}

$packageDirectory = dirname(__DIR__);
$vendorDirectory = $packageDirectory . '/vendor';
require $vendorDirectory . '/autoload.php';
$_SERVER['COLLISION_PRINTER'] = 'DefaultPrinter';
$GLOBALS['_composer_bin_dir'] = $vendorDirectory . '/bin';

$arguments = $_SERVER['argv'];
foreach ($arguments as &$argument) {
    if (str_starts_with($argument, '--filter="') && str_ends_with($argument, '"')) {
        $argument = '--filter=' . substr($argument, 10, -1);
    }
}
unset($argument);
if (in_array('--compact', $arguments, true)) {
    $_SERVER['COLLISION_PRINTER_COMPACT'] = 'true';
    $arguments = array_values(array_filter($arguments, static fn (string $argument): bool => $argument !== '--compact'));
}
$_SERVER['argv'] = $arguments;

if (getenv('PEST_MUTATION_TESTING') !== false) {
    register_shutdown_function(static function (): void {
        if (class_exists(StreamWrapper::class, false)) {
            StreamWrapper::disable();
        }
    });
}

define('COMPONENTA_CQRS_MUTATION_ARGUMENTS', $arguments);
$input = new ArgvInput($arguments);
$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, false);
$kernel = Kernel::boot(TestSuite::getInstance($packageDirectory, 'tests'), $input, $output);
exit($kernel->handle($arguments, $arguments));
