<?php

declare(strict_types=1);

namespace App\Console;

use App\ApplicationServices;
use App\Import\Csv\CsvReadException;
use App\Import\Model\ImportPreview;
use App\Import\Model\ImportResult;
use App\Import\Model\ProcessedRow;
use App\Import\Model\ValidationError;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class UserUploadCommand extends Command
{
    public function __construct(
        private readonly Closure $services,
    ) {
        parent::__construct('users:import');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Preview or import users from a CSV file.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'CSV file to process')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate without importing')
            ->addOption('create-table', null, InputOption::VALUE_NONE, 'Create or rebuild the users table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($input->getOption('create-table') === true) {
                $this->services()->schemaManager->rebuild();
                $io->success('The users table was rebuilt successfully.');

                return Command::SUCCESS;
            }

            $file = $input->getOption('file');
            if (!is_string($file) || trim($file) === '') {
                $io->error('Provide a CSV file with --file <filename>.');

                return Command::INVALID;
            }

            if (!is_file($file) || !is_readable($file)) {
                $io->error('The CSV file could not be read.');

                return Command::FAILURE;
            }

            $service = $this->services()->userImportService;

            if ($input->getOption('dry-run') === true) {
                $preview = $service->preview($file);
                $this->renderPreview($io, $preview);
                $io->note('Dry run complete. No users were imported.');

                return Command::SUCCESS;
            }

            $result = $service->import($file);
            $this->renderResult($io, $result);

            return Command::SUCCESS;
        } catch (CsvReadException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable) {
            $io->error('The operation failed. Check the database configuration and try again.');

            return Command::FAILURE;
        }
    }

    private function services(): ApplicationServices
    {
        return ($this->services)();
    }

    private function renderPreview(SymfonyStyle $io, ImportPreview $preview): void
    {
        $io->section('Import preview');
        $io->definitionList(
            ['Users found' => (string) $preview->total],
            ['Valid' => (string) $preview->valid],
            ['Invalid' => (string) $preview->invalid],
        );
        $this->renderInvalidRows($io, $preview->rows);
    }

    private function renderResult(SymfonyStyle $io, ImportResult $result): void
    {
        $io->success(sprintf('Imported %d user%s.', $result->imported, $result->imported === 1 ? '' : 's'));
        $io->definitionList(
            ['Users found' => (string) $result->total],
            ['Valid' => (string) $result->valid],
            ['Invalid' => (string) $result->invalid],
            ['Imported' => (string) $result->imported],
            ['Skipped during import' => (string) $result->skipped],
        );
        $this->renderInvalidRows($io, $result->rows);
    }

    /** @param list<ProcessedRow> $rows */
    private function renderInvalidRows(SymfonyStyle $io, array $rows): void
    {
        $invalidRows = array_filter($rows, static fn (ProcessedRow $row): bool => !$row->isValid());
        if ($invalidRows === []) {
            return;
        }

        $io->section('Rows requiring attention');
        $io->table(
            ['CSV row', 'Email', 'Errors'],
            array_map(
                static fn (ProcessedRow $row): array => [
                    $row->rowNumber,
                    $row->candidate->email !== '' ? $row->candidate->email : '(missing)',
                    implode('; ', array_map(
                        static fn (ValidationError $error): string => $error->message,
                        $row->errors,
                    )),
                ],
                $invalidRows,
            ),
        );
    }
}
