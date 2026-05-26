<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Import\ImportPortalCsv;
use Defibrion\Samenstellingen\Application\Import\ImportPortalCsvHandler;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:import-portal-csv',
    description: 'Wis de tool-data en herimporteer groepen/bases/items uit de AED-portal CSV met AFAS-BOM auto-fill.',
)]
final class ImportPortalCsvCommand extends Command
{
    public function __construct(private readonly ImportPortalCsvHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('csv-file', InputArgument::REQUIRED, 'Pad naar de portal-CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvPath = (string) $input->getArgument('csv-file');

        try {
            $summary = ($this->handler)(new ImportPortalCsv($csvPath));
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'Verwerkt: %d rijen (%d zonder Groep overgeslagen).',
            $summary->rowsProcessed,
            $summary->rowsSkippedNoGroep,
        ));

        if ($summary->unresolved !== []) {
            $ambiguous = 0;
            $missing = 0;
            foreach ($summary->unresolved as $entry) {
                if (str_starts_with($entry['reason'], 'Ambigu')) {
                    ++$ambiguous;
                } else {
                    ++$missing;
                }
            }

            $io->warning(sprintf(
                '%d rij(en) overgeslagen: %d zonder base-kandidaat, %d ambigu in AFAS. Resolveerbare rijen zijn wel geïmporteerd.',
                count($summary->unresolved),
                $missing,
                $ambiguous,
            ));

            $rows = [];
            foreach ($summary->unresolved as $entry) {
                $rows[] = [$entry['code'], $entry['articleName'], $entry['reason']];
            }
            $io->section('Onresolveerbare article-codes');
            $io->table(['Code', 'AFAS-artikel', 'Reden'], $rows);

            $io->writeln('<comment>Actie voor de volgende ronde:</comment>');
            if ($missing > 0) {
                $io->writeln(sprintf(
                    '  • Voor %d code(s) zonder kandidaat: maak in AFAS een base-samenstelling aan (BOM met article, reanimatiekit 70112 en stickerset 81xxx, zonder geregistreerde accessoire).',
                    $missing,
                ));
            }
            if ($ambiguous > 0) {
                $io->writeln(sprintf(
                    '  • Voor %d ambigue code(s): los de duplicate base-samenstellingen op in AFAS (verwijder de overbodige of corrigeer de BOM) of breid de accessoires-catalogus/BOM-blacklist uit.',
                    $ambiguous,
                ));
            }
            $io->writeln('  • Draai daarna `afas:pull` en herhaal de import.');
            $io->newLine();
        }

        $io->table(
            ['Categorie', 'Aantal'],
            [
                ['Groepen aangemaakt', (string) $summary->groupsCreated],
                ['Bases aangemaakt', (string) $summary->basesCreated],
                ['Base-items aangemaakt', (string) $summary->baseItemsCreated],
            ],
        );

        $sync = $summary->sync;
        if ($sync !== null && $sync->groupsProcessed > 0) {
            $io->writeln(sprintf(
                'Auto-sync: %d groepen verwerkt → <info>%d matched</info>, <comment>%d no_match</comment>.',
                $sync->groupsProcessed,
                $sync->matched,
                $sync->noMatch,
            ));
        } elseif ($sync !== null && $sync->groupsSkipped > 0) {
            foreach ($sync->skipReasons as $reason) {
                $io->writeln('<comment>Auto-sync overgeslagen:</comment> ' . $reason);
            }
        }

        $io->success(sprintf("Import van '%s' afgerond.", $csvPath));

        return Command::SUCCESS;
    }
}
