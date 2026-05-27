<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditDuplicateBoms;
use Defibrion\Samenstellingen\Application\Audit\DuplicateBomAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:duplicate-boms',
    description: 'Detecteer AFAS-samenstellingen met identieke BOM. Vaak varianten waar het accessoire-itemcode niet aan de BOM is toegevoegd.',
)]
final class AuditDuplicateBomsCommand extends Command
{
    public function __construct(private readonly DuplicateBomAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk output tot N groepen', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = ($this->handler)(new AuditDuplicateBoms());

        if ($groups === []) {
            $io->success('Geen duplicate BOMs gevonden — alle AFAS-samenstellingen hebben een unieke BOM.');

            return Command::SUCCESS;
        }

        $totalMembers = 0;
        foreach ($groups as $g) {
            $totalMembers += count($g->members);
        }

        $limit = (int) $input->getOption('limit');
        $shown = $limit > 0 ? array_slice($groups, 0, $limit) : $groups;

        $rows = [];
        foreach ($shown as $g) {
            $itemcodes = array_map(static fn (array $m): string => $m['itemcode'], $g->members);
            $rows[] = [
                $g->fingerprint,
                count($g->members),
                implode(', ', $itemcodes),
            ];
        }
        $io->section(sprintf(
            '%d groep(en) met %d totaal-samenstellingen — gemeenschappelijke BOM',
            count($groups),
            $totalMembers,
        ));
        $io->table(['BOM-fingerprint', 'Aantal', 'Itemcodes'], $rows);

        if ($limit > 0 && count($groups) > $limit) {
            $io->writeln(sprintf('<comment>%d groep(en) niet getoond.</comment>', count($groups) - $limit));
        }

        return Command::FAILURE;
    }
}
