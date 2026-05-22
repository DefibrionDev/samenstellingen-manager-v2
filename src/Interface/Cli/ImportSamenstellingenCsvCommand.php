<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Import\ImportSamenstellingenCsv;
use Defibrion\Samenstellingen\Application\Import\ImportSamenstellingenCsvHandler;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:import-csv',
    description: 'Importeer bases en accessoires uit een CSV (kolommen: samenstelling_itemcode, samenstelling_naam, aed_article, aed_article_naam).',
)]
final class ImportSamenstellingenCsvCommand extends Command
{
    public function __construct(private readonly ImportSamenstellingenCsvHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv-file', InputArgument::REQUIRED, 'Pad naar het CSV-bestand')
            ->addArgument('family-head-itemcode', InputArgument::REQUIRED, 'Family-head itemcode van de bestaande groep');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvPath = (string) $input->getArgument('csv-file');
        $familyHead = (string) $input->getArgument('family-head-itemcode');

        try {
            $summary = ($this->handler)(new ImportSamenstellingenCsv($csvPath, $familyHead));
        } catch (GroupNotFoundException | RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(sprintf('Verwerkt: %d rijen.', $summary->rowsProcessed));
        $io->table(
            ['Categorie', 'Aangemaakt', 'Overgeslagen'],
            [
                ['Bases', (string) $summary->basesCreated, (string) $summary->basesSkipped],
                ['Accessoires (catalogus)', (string) $summary->accessoiresCreated, (string) $summary->accessoiresSkipped],
                ['Accessoire-links', (string) $summary->accessoireLinksCreated, (string) $summary->accessoireLinksSkipped],
            ],
        );

        $io->success(sprintf(
            "Import van '%s' afgerond voor groep %s.",
            $csvPath,
            $familyHead,
        ));

        return Command::SUCCESS;
    }
}
