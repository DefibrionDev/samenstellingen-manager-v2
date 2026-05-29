<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixMissingVariants;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariantsWithPricesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'variants:fix-missing',
    description: 'Maak ontbrekende variant-samenstellingen aan in AFAS via FbComposition POST. Default dry-run.',
)]
final class FixMissingVariantsCommand extends Command
{
    public function __construct(private readonly FixMissingVariantsWithPricesHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt POST naar AFAS. Default = dry-run.')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Beperk tot één groep (family-head itemcode).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N missende variants.', '0')
            ->addOption('skip-prices', null, InputOption::VALUE_NONE, 'Sla automatische prijs-insert over na variant-POST.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');
        $skipPrices = (bool) $input->getOption('skip-prices');
        $groupOption = $input->getOption('group');
        $group = is_string($groupOption) && $groupOption !== '' ? $groupOption : null;

        $chained = ($this->handler)(new FixMissingVariants(
            apply: $apply,
            familyHeadItemcode: $group,
            limit: $limit > 0 ? $limit : null,
            skipPrices: $skipPrices,
        ));
        $result = $chained->variants;

        if ($result->skipped !== []) {
            $io->section(sprintf('%d overgeslagen', count($result->skipped)));
            $rows = [];
            foreach ($result->skipped as $skip) {
                $rows[] = [$skip['itemcode'], $this->ellipsize($skip['reason'], 80)];
            }
            $io->table(['Itemcode', 'Reden'], $rows);
        }

        if ($result->plans === []) {
            $io->success('Geen missende varianten gevonden — alles aanwezig in AFAS of overgeslagen.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->plans as $p) {
            $rows[] = [
                $p->afasItemcode,
                $this->ellipsize($p->canonicalName, 60),
                (string) count($p->bomItemcodes),
                $p->familyHeadItemcode,
            ];
        }
        $io->section(sprintf('%d missende variant(en) — %s', count($result->plans), $apply ? 'APPLY' : 'dry-run'));
        $io->table(['Itemcode', 'Canonical naam', 'BOM-items', 'Family-head'], $rows);

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen AFAS-mutaties. Run met --apply om varianten + prijzen chained te POSTen.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d aangemaakt</info>, <comment>%d gefaald</comment>.',
            $result->appliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/fix-variants-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['itemcode', 'canonical_name', 'family_head', 'bom', 'error']);
                foreach ($result->failures as $f) {
                    fputcsv($fh, [
                        $f['plan']->afasItemcode,
                        $f['plan']->canonicalName,
                        $f['plan']->familyHeadItemcode,
                        implode(',', $f['plan']->bomItemcodes),
                        $f['error'],
                    ]);
                }
                fclose($fh);
                $io->writeln(sprintf('Failures gelogd naar <comment>%s</comment>.', $csv));
            }
        }

        if ($chained->prices !== null) {
            $io->writeln('');
            $io->section(sprintf('Chained prijzen (na refresh) — %d basis/staffel-prijzen', count($chained->prices->plans)));
            if ($chained->prices->plans !== []) {
                $priceRows = [];
                foreach ($chained->prices->plans as $p) {
                    $priceRows[] = [
                        $p->variantItemcode,
                        $p->prijslijstId,
                        $p->staffelAantal === null ? 'basis' : (string) $p->staffelAantal,
                        sprintf('€ %.2f', $p->targetCents / 100),
                        $p->beginDate,
                    ];
                }
                $io->table(['Variant', 'Lijst', 'Aantal', 'Prijs', 'Begindatum'], $priceRows);
                $io->writeln(sprintf(
                    '<info>%d prijzen ingevoegd</info>, <comment>%d gefaald</comment>.',
                    $chained->prices->appliedCount,
                    count($chained->prices->failures),
                ));
            }
        } elseif ($result->appliedCount > 0 && $skipPrices) {
            $io->writeln('');
            $io->writeln('<comment>--skip-prices actief — draai handmatig `afas:pull && prices:fix-missing --apply` voor prijzen.</comment>');
        }

        return $result->failures === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function ellipsize(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
    }
}
