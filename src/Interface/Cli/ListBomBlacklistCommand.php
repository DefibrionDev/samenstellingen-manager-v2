<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Afas\ListBomBlacklist;
use Defibrion\Samenstellingen\Application\Afas\ListBomBlacklistHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'samenstelling:list-blacklist',
    description: 'Toon alle BOM-itemcodes op de base-kandidaat-blacklist.',
)]
final class ListBomBlacklistCommand extends Command
{
    public function __construct(private readonly ListBomBlacklistHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entries = ($this->handler)(new ListBomBlacklist());

        if ($entries === []) {
            $io->writeln('<comment>Geen BOM-itemcodes op de blacklist.</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [$entry->itemcode, $entry->reason];
        }
        $io->table(['Itemcode', 'Reden'], $rows);

        return Command::SUCCESS;
    }
}
