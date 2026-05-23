<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariants;
use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:export-missing',
    description: 'Exporteer alle variant-combinaties die niet in AFAS bestaan (no_match) als CSV — actie-lijst voor het AFAS-team.',
)]
final class ExportMissingVariantsCommand extends Command
{
    public function __construct(private readonly ListMissingVariantsHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('output-csv', InputArgument::REQUIRED, 'Pad naar het CSV-bestand om naartoe te schrijven');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = (string) $input->getArgument('output-csv');

        $rows = ($this->handler)(new ListMissingVariants());

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf("Kon directory niet aanmaken: '%s'.", $dir));
            }
        }

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $io->error(sprintf("Kon bestand niet openen voor schrijven: '%s'.", $outputPath));

            return Command::FAILURE;
        }

        try {
            fputcsv($handle, [
                'groep',
                'base_naam',
                'base_afas_sku',
                'accessoire_itemcode',
                'accessoire_label',
                'verwachte_bom',
                'verwachte_sku_voorstel',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->groep,
                    $row->baseNaam,
                    $row->baseAfasSku,
                    $row->accessoireItemcode,
                    $row->accessoireLabel,
                    implode(',', $row->verwachteBom),
                    $row->verwachteSkuVoorstel,
                ]);
            }
        } finally {
            fclose($handle);
        }

        $io->success(sprintf(
            '%d ontbrekende varianten geëxporteerd naar %s.',
            count($rows),
            $outputPath,
        ));

        return Command::SUCCESS;
    }
}
