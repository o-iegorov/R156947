<?php
declare(strict_types=1);

namespace R156947\Json;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Deduplicator
{
    /**
     * Directory where leads file is located
     */
    public const LEADS_FILE_DIR = __DIR__ . '/../../';

    private string $outputFileName = '';

    private string $inputFileName = '';

    private Logger $logger;

    public function __construct() {
        $this->logger = new Logger('R156947');
        $this->logger->pushHandler(
            new StreamHandler(__DIR__ . '/../../var/log/application.log',
                Level::Info)
        );
    }

    /**
     * Extract values of a specific column from the given array of associative arrays
     *
     * @param array $data
     * @param string $columnName
     * @return array
     */
    private function getColumnValues(array $data, string $columnName): array {
        return array_column($data, $columnName);
    }

    /**
     * Find duplicate values in the given array and return their indexes
     *
     * @param array $data
     * @param array $duplicatesIndexes
     * @return array
     */
    private function findDuplicates(array $data, array $duplicatesIndexes = []): array {
        $counts = array_count_values($data);
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                $isNewGroup = true;
                $currentGroup = array_keys($data, $key);
                foreach ($duplicatesIndexes as $index => $duplicateIndexGroup) {
                    if (array_intersect($duplicateIndexGroup, $currentGroup)) {
                        $isNewGroup = false;
                        $duplicatesIndexes[$index] = array_unique(array_merge($duplicateIndexGroup, $currentGroup));
                        sort($duplicatesIndexes[$index]);
                    }
                }
                if ($isNewGroup === true) {
                    $duplicatesIndexes[] = $currentGroup;
                }
            }
        }
        return $duplicatesIndexes;
    }

    /**
     * Find the most recent lead in the duplicated group
     *
     * The most recent lead is determined by the entryDate field, and if there are multiple leads
     * with the same entryDate, the one with the highest index in the original leads array will be kept.
     * All leads in the duplicated group except the most recent one will be removed from the leads array with logging
     *
     * @param array $leads
     * @param array $duplicatedGroup
     * @return array
     */
    private function processDuplicatedGroup(array $leads, array $duplicatedGroup): array {
        $dates = [];
        foreach ($duplicatedGroup as $currentIndex) {
            $dates[] = ['index' => $currentIndex, 'entryDate' => strtotime($leads[$currentIndex]['entryDate'])];
        }
        usort($dates, function($entry1, $entry2) {
            if ($entry1['entryDate'] === $entry2['entryDate']) {
                return $entry1['index'] <=> $entry2['index'];
            }
            return $entry1['entryDate'] <=> $entry2['entryDate'];
        });
        $lastEntry = end($dates)['index'];
        $inexToKeep = array_search($lastEntry, $duplicatedGroup);
        $itemToKeep = json_encode($leads[$inexToKeep]);
        unset($duplicatedGroup[$inexToKeep]);
        foreach ($duplicatedGroup as $currentIndex) {
            $item = json_encode($leads[$currentIndex]);
            $this->logger->info('Removing duplicated lead: ' . $item . ' in favor of the lead: ' . $itemToKeep);
        }
        return array_diff_key($leads, array_flip($duplicatedGroup));
    }

    /**
     * Save the filtered leads data to the output file
     *
     * @param array $data
     */
    private function saveFilteredLeads(array $data): void {
        $data['leads'] = array_values($data['leads']);

        // tweak JSON to have same formatting as in the provided file
        $output = preg_replace('/^ +/m', '', json_encode($data, JSON_PRETTY_PRINT));
        $output = str_replace("}\n]\n", "}]\n", $output);
        $output = str_replace("{\n\"leads\": [", "{\"leads\":[", $output);

        file_put_contents(self::LEADS_FILE_DIR . $this->outputFileName, $output);
    }

    /**
     * Execute the deduplication process
     *
     * @param string $inputFileName Name of the input file containing leads data
     * @param string $outputFileName Name of the output file where filtered leads will be saved
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function execute(string $inputFileName, string $outputFileName): void
    {
        $this->inputFileName = $inputFileName;
        $this->outputFileName = $outputFileName;
        if (file_exists(self::LEADS_FILE_DIR . $this->inputFileName)) {
            $jsonContent = file_get_contents(self::LEADS_FILE_DIR . $this->inputFileName);
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

            $ids = $this->getColumnValues($data['leads'], '_id');
            $emails = $this->getColumnValues($data['leads'], 'email');

            $duplicatedGroups = $this->findDuplicates($ids);
            $duplicatedGroups = $this->findDuplicates($emails, $duplicatedGroups);

            foreach ($duplicatedGroups as $duplicatedGroup) {
                $data['leads'] = $this->processDuplicatedGroup($data['leads'], $duplicatedGroup);
            }

            $this->saveFilteredLeads($data);
        } else {
            throw new \RuntimeException('Unable to load ' . $this->inputFileName . ' file.');
        }
    }
}