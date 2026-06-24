<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Publications\ListOnlineNotAssignedHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:online-not-assigned',
    description: 'Itemcodes die in AFAS online staan (Sync/Tonen) maar in de tool niet aan die website zijn toegekend. Read-only; leest de lokale snapshot (ververst bij afas:pull).',
)]
final class AuditOnlineNotAssignedCommand extends Command
{
    public function __construct(private readonly ListOnlineNotAssignedHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)();

        if ($rows === []) {
            $io->success('Niets — geen itemcodes die online staan zonder toekenning in de tool.');

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [$row->afasItemcode, $row->baseAfasItemcode, $row->websiteName];
        }
        $io->table(['itemcode', 'base', 'website'], $tableRows);
        $io->note(sprintf(
            '%d itemcode×website staat online in AFAS maar is niet toegekend in de tool. '
            . 'De publicatie-sync raakt deze niet aan — toekennen via `base:publish` of in AFAS uitzetten.',
            count($rows),
        ));

        return Command::SUCCESS;
    }
}
