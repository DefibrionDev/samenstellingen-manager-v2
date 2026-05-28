<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixPriceDrift;
use Defibrion\Samenstellingen\Application\Fix\FixPriceDriftHandler;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'prices:fix-drift',
    description: 'Corrigeer toeslag-drift in AFAS: schrijf variant-prijzen naar base + accessoires.delta_cents via FbSalesPrice. Default dry-run.',
)]
final class FixPriceDriftCommand extends Command
{
    public function __construct(private readonly FixPriceDriftHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt schrijven naar AFAS. Default = dry-run.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N plannen (eerste N drift-rijen).', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');

        $result = ($this->handler)(new FixPriceDrift(
            apply: $apply,
            limit: $limit > 0 ? $limit : null,
        ));

        if ($result->plans === []) {
            $io->success('Geen drift te fixen — alle variant-prijzen kloppen al.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->plans as $p) {
            $rows[] = [
                $p->variantItemcode,
                $p->prijslijstId,
                $p->staffelAantal !== null ? (string) $p->staffelAantal : 'basis',
                EuroParser::formatCents($p->currentCents),
                EuroParser::formatCents($p->targetCents),
                EuroParser::formatCents($p->differenceCents()),
            ];
        }
        $io->section(sprintf('%d drift-rij(en) — %s', count($result->plans), $apply ? 'APPLY' : 'dry-run'));
        $io->table(['Variant', 'Lijst', 'Aantal', 'Huidig', 'Gewenst', 'Δ'], $rows);

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen AFAS-mutaties. Run met --apply om echt te schrijven.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d geschreven</info>, <comment>%d gefaald</comment>.',
            $result->appliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/fix-drift-%s.csv', date('Y-m-d-His'));
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
