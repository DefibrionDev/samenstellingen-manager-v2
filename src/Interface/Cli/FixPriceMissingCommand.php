<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixPriceMissing;
use Defibrion\Samenstellingen\Application\Fix\FixPriceMissingHandler;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'prices:fix-missing',
    description: 'Voeg ontbrekende variant-prijzen toe in AFAS via FbSalesPrice POST. Default dry-run. Skipt variants die niet als artikel in AFAS bestaan.',
)]
final class FixPriceMissingCommand extends Command
{
    public function __construct(private readonly FixPriceMissingHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt POST naar AFAS. Default = dry-run.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N inserts.', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');

        $result = ($this->handler)(new FixPriceMissing(
            apply: $apply,
            limit: $limit > 0 ? $limit : null,
        ));

        if ($result->skippedNoArticle !== []) {
            $io->writeln(sprintf(
                '<comment>%d variant(s) overgeslagen — artikel bestaat niet in AFAS (zie slice 13).</comment>',
                count($result->skippedNoArticle),
            ));
        }

        if ($result->plans === []) {
            $io->success('Geen missing prijzen te POST\'en (geen plans gegenereerd).');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->plans as $p) {
            $rows[] = [
                $p->variantItemcode,
                $p->prijslijstId,
                $p->staffelAantal !== null ? (string) $p->staffelAantal : 'basis',
                EuroParser::formatCents($p->targetCents),
                $p->beginDate,
            ];
        }
        $io->section(sprintf('%d missing prijs(prijzen) — %s', count($result->plans), $apply ? 'APPLY' : 'dry-run'));
        $io->table(['Variant', 'Lijst', 'Aantal', 'Prijs', 'Begindatum'], $rows);

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen AFAS-mutaties. Run met --apply om echt te POST\'en.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d ingevoegd</info>, <comment>%d gefaald</comment>.',
            $result->appliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/fix-missing-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['itemcode', 'prijslijst', 'staffel', 'error']);
                foreach ($result->failures as $f) {
                    fputcsv($fh, [
                        $f['plan']->variantItemcode,
                        $f['plan']->prijslijstId,
                        $f['plan']->staffelAantal !== null ? (string) $f['plan']->staffelAantal : 'basis',
                        $f['error'],
                    ]);
                }
                fclose($fh);
                $io->writeln(sprintf('Failures gelogd naar <comment>%s</comment>.', $csv));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
