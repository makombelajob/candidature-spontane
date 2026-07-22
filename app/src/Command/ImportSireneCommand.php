<?php

namespace App\Command;

use App\Service\SireneImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-sirene', description: 'Import a SIRENE CSV file into a local SQLite database')]
final class ImportSireneCommand extends Command
{
    public function __construct(private readonly SireneImporter $sireneImporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::OPTIONAL, 'Path to the CSV file to import', 'var/data/StockEtablissement.csv')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Path to the SQLite database', 'var/data/data.db')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of rows to insert per batch', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputPath = $input->getArgument('input');
        $outputPath = $input->getOption('output');
        $batchSize = (int) $input->getOption('batch-size');

        if ($batchSize < 1) {
            $output->writeln('<error>Batch size must be at least 1.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Importing %s</info>', $inputPath));

        try {
            $count = $this->sireneImporter->import($inputPath, $outputPath, $batchSize, static function (int $processed) use ($output): void {
                $output->write(sprintf("\rProcessed rows: %d", $processed));
            });
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Import completed. %d rows inserted into %s</info>', $count, $outputPath));

        return Command::SUCCESS;
    }
}
