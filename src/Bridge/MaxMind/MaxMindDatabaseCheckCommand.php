<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\MaxMind;

use GeoIp2\Database\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'timezone:check', description: 'Checks the configured local MaxMind City database.')]
final class MaxMindDatabaseCheckCommand extends Command
{
    /** @var \Closure(string): object */
    private readonly \Closure $metadataFactory;

    /** @param null|callable(string): object $metadataFactory */
    public function __construct(private readonly string $databasePath, ?callable $metadataFactory = null)
    {
        parent::__construct();
        $this->metadataFactory = null === $metadataFactory
            ? static function (string $path): object {
                $reader = new Reader($path);

                return $reader->metadata();
            }
            : $metadataFactory(...);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!is_readable($this->databasePath)) {
            $output->writeln('<error>The MaxMind database is not readable.</error>');

            return self::FAILURE;
        }

        try {
            $metadata = ($this->metadataFactory)($this->databasePath);
            $databaseType = property_exists($metadata, 'databaseType') && is_string($metadata->databaseType)
                ? $metadata->databaseType
                : '';
            if (!str_contains($databaseType, 'City')) {
                $output->writeln('<error>The MaxMind database is not a City database.</error>');

                return self::FAILURE;
            }
        } catch (\Exception) {
            $output->writeln('<error>The MaxMind database could not be opened.</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>The MaxMind City database is readable and valid.</info>');

        return self::SUCCESS;
    }
}
