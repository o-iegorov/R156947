<?php
declare(strict_types=1);

namespace R156947\Command;

use R156947\Json\Deduplicator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class DeduplicateCommand extends Command
{
    /**
     * Default name of the output file where filtered leads will be saved
     */
    public const FILTERED_LEADS_FILE = 'leads-filtered.json';

    /**
     * Default name of the input file where leads are read from
     */
    public const LEADS_FILE = 'leads.json';

    private Deduplicator $deduplicator;

    private Logger $logger;

    protected static string $defaultName = 'json:deduplicate';

    public function __construct() {
        parent::__construct(self::$defaultName);
        $this->deduplicator = new Deduplicator();
        $this->logger = new Logger('R156947');
    }


    /**
     * CLI command configuration
     */
    protected function configure(): void
    {
        $this->setName(self::$defaultName)
            ->setDescription('Removes duplicate entries from a JSON file based on id and email')
            ->setHelp('The output will be JSON file without duplicated entries.')
            ->addOption(
                'input-file',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Input file name, default name is ' . self::LEADS_FILE,
            )
            ->addOption(
                'output-file',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output file name, default name is ' . self::FILTERED_LEADS_FILE,
            );
    }

    /**
     * Processes the input file to remove duplicates and saves the result to the output file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $inputFile = $input->getOption('input-file') ?: self::LEADS_FILE;
            $outputFile = $input->getOption('output-file') ?: self::FILTERED_LEADS_FILE;
            $this->deduplicator->execute($inputFile, $outputFile);
            $output->writeln("Filtered leads have been saved to " . $outputFile);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->pushHandler(
                new StreamHandler(__DIR__ . '/../../var/log/application.log',
                    Level::Error)
            );
            $this->logger->error('An error occurred while deduplicating leads: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
