<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricelist:list-whitelist',
    description: 'Toon de prijslijst-whitelist (ID, omschrijving, reden, aangemaakt-op).',
)]
final class ListPrijslijstWhitelistCommand extends Command
{
    public function __construct(
        private readonly PrijslijstWhitelistRepository $whitelist,
        private readonly AfasPrijslijstRepository $prijslijsten,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entries = $this->whitelist->findAll();

        if ($entries === []) {
            $io->writeln('<comment>Geen prijslijsten op de whitelist.</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $e) {
            $prijslijst = $this->prijslijsten->findById($e->prijslijstId);
            $rows[] = [
                $e->prijslijstId,
                $prijslijst !== null ? $prijslijst->omschrijving : '(onbekend)',
                $e->reden,
                $e->aangemaaktOp ?? '',
            ];
        }
        $io->table(['ID', 'Omschrijving', 'Reden', 'Aangemaakt-op'], $rows);

        return Command::SUCCESS;
    }
}
