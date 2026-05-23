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

        // Bij onresolveerbare rijen: geen import uitgevoerd, alleen lijst tonen.
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

            $io->error(sprintf(
                '%d rij(en) konden niet worden gemapt: %d zonder base-kandidaat, %d ambigu in AFAS. Geen import uitgevoerd — DB is onveranderd.',
                count($summary->unresolved),
                $missing,
                $ambiguous,
            ));

            $rows = [];
            foreach ($summary->unresolved as $entry) {
                $rows[] = [$entry['groep'], $entry['code'], $entry['reason']];
            }
            $io->section('Onresolveerbare article-codes');
            $io->table(['Groep', 'Code', 'Reden'], $rows);

            $io->writeln('<comment>Actie:</comment>');
            if ($missing > 0) {
                $io->writeln(sprintf(
                    '  • Voor %d code(s) zonder kandidaat: maak in AFAS een base-samenstelling aan (BOM met article, reanimatiekit 70112 en stickerset 81xxx, zonder geregistreerde accessoire).',
                    $missing,
                ));
            }
            if ($ambiguous > 0) {
                $io->writeln(sprintf(
                    '  • Voor %d ambigue code(s): los de duplicate base-samenstellingen op in AFAS (verwijder de overbodige of corrigeer de BOM).',
                    $ambiguous,
                ));
            }
            $io->writeln('  • Draai daarna `afas:pull` en herhaal de import.');

            return Command::FAILURE;
        }

        $io->table(
            ['Categorie', 'Aantal'],
            [
                ['Groepen aangemaakt', (string) $summary->groupsCreated],
                ['Bases aangemaakt', (string) $summary->basesCreated],
                ['Base-items aangemaakt', (string) $summary->baseItemsCreated],
            ],
        );

        $io->success(sprintf("Import van '%s' afgerond.", $csvPath));

        return Command::SUCCESS;
    }
}
