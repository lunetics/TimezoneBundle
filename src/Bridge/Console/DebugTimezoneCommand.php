<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'debug:timezone', description: 'Displays the configured timezone resolver order.')]
final class DebugTimezoneCommand extends Command
{
    /** @param list<array{name: string, priority: int}> $resolvers */
    public function __construct(private readonly string $configuredDefault, private readonly array $resolvers)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln(sprintf('Configured default: %s', $this->configuredDefault));
        $io->table(['Resolver', 'Priority'], array_map(
            static fn (array $resolver): array => [$resolver['name'], (string) $resolver['priority']],
            $this->resolvers,
        ));

        return self::SUCCESS;
    }
}
