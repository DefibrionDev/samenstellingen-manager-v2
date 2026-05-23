<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditSuspiciousBases;
use Defibrion\Samenstellingen\Application\Audit\SuspiciousBaseAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:suspicious-bases',
    description: 'Detecteer AFAS-samenstellingen waarvan de SKU op een geregistreerde accessoire-itemcode eindigt, terwijl de BOM die accessoire niet bevat.',
)]
final class AuditSuspiciousBasesCommand extends Command
{
    public function __construct(private readonly SuspiciousBaseAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditSuspiciousBases());

        if ($rows === []) {
            $io->success('Geen verdachte bases gevonden — alle SKU-suffixen kloppen met de BOM.');

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->afasItemcode,
                $row->expectedAccessoireItemcode . ' — ' . $row->expectedAccessoireLabel,
                $row->name,
                implode(', ', $row->bom),
            ];
        }
        $io->section(sprintf('%d verdachte base(s)', count($rows)));
        $io->table(['SKU', 'Verwachte accessoire', 'Naam', 'BOM in AFAS'], $tableRows);
        $io->writeln('<comment>Actie:</comment> voeg de verwachte accessoire-itemcode toe aan de BOM van de samenstelling in AFAS, draai daarna `afas:pull` om het lokaal te corrigeren.');

        return Command::FAILURE;
    }
}
