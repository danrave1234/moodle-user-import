<?php

declare(strict_types=1);

use App\Console\UserUploadCommand;
use Symfony\Component\Console\Application;

require __DIR__ . '/vendor/autoload.php';

$application = new Application('Moodle User Import', '1.0.0');
$application->add(new UserUploadCommand(
    static fn (): App\ApplicationServices => require __DIR__ . '/bootstrap.php',
));
$application->setDefaultCommand('users:import', true);
$application->run();
