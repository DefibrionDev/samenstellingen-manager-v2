<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\BasePriceGapsHandler;
use Defibrion\Samenstellingen\Application\Audit\ListBasePriceGaps;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:base-prices',
    description: 'Lijst managed base-samenstellingen die ontbreken in een whitelist-prijslijst (geen prijslijst-prijs aanwezig).',
)]
final class AuditBasePricesCommand extends Command
{
    public function __construct(private readonly BasePriceGapsHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new ListBasePriceGaps());

        if ($rows === []) {
            $io->success('Elke managed base staat in alle whitelist-prijslijsten.');

            return Command::SUCCESS;
        }

        $perLijst = [];
        $tableRows = [];
        foreach ($rows as $row) {
            $lijstLabel = $row->prijslijstOmschrijving !== null
                ? sprintf('%s — %s', $row->prijslijstId, $row->prijslijstOmschrijving)
                : $row->prijslijstId;
            $perLijst[$lijstLabel] = ($perLijst[$lijstLabel] ?? 0) + 1;
            $tableRows[] = [
                $lijstLabel,
                $row->baseAfasItemcode,
                $row->groupName,
                $row->baseName,
            ];
        }
        $io->table(
            ['prijslijst', 'base-itemcode', 'groep', 'base-naam'],
            $tableRows,
        );

        $perLijstParts = [];
        foreach ($perLijst as $label => $count) {
            $perLijstParts[] = sprintf('%s: %d', $label, $count);
        }
        $io->note(sprintf(
            '%d base(s) ontbreken in een whitelist-prijslijst — %s. '
            . 'Read-only signaal: de prijs moet AFAS-zijdig aangemaakt worden.',
            count($rows),
            implode(', ', $perLijstParts),
        ));

        return Command::SUCCESS;
    }
}
