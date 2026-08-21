<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\ApplicationServices;
use App\Console\UserUploadCommand;
use PDOException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class UserUploadCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'console-import-');
        self::assertIsString($path);
        file_put_contents($path, "name,surname,email\nJohn,Smith,john@example.com\n");
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testHelpDoesNotResolveDatabaseServices(): void
    {
        $resolved = false;
        $command = new UserUploadCommand(static function () use (&$resolved): ApplicationServices {
            $resolved = true;
            throw new PDOException('must not resolve');
        });
        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $tester = new ApplicationTester($application);

        self::assertSame(Command::SUCCESS, $tester->run(['command' => 'users:import', '--help' => true]));
        self::assertFalse($resolved);
        self::assertStringContainsString('--dry-run', $tester->getDisplay());
    }

    public function testMissingFileOptionReturnsInvalidWithoutResolvingServices(): void
    {
        $tester = new CommandTester(new UserUploadCommand(self::failingResolver()));

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertStringContainsString('Provide a CSV file', $tester->getDisplay());
    }

    public function testMissingCsvReturnsFailureWithoutResolvingServices(): void
    {
        $tester = new CommandTester(new UserUploadCommand(self::failingResolver()));

        self::assertSame(Command::FAILURE, $tester->execute(['--file' => $this->path.'.missing']));
        self::assertStringContainsString('could not be read', $tester->getDisplay());
    }

    public function testInfrastructureFailureIsSanitized(): void
    {
        $tester = new CommandTester(new UserUploadCommand(static function (): ApplicationServices {
            throw new PDOException('password=top-secret');
        }));

        self::assertSame(Command::FAILURE, $tester->execute(['--file' => $this->path]));
        self::assertStringContainsString('Check the database configuration', $tester->getDisplay());
        self::assertStringNotContainsString('top-secret', $tester->getDisplay());
        self::assertStringNotContainsString('PDOException', $tester->getDisplay());
    }

    /** @return \Closure(): ApplicationServices */
    private static function failingResolver(): \Closure
    {
        return static function (): ApplicationServices {
            throw new PDOException('services should not be resolved');
        };
    }
}
