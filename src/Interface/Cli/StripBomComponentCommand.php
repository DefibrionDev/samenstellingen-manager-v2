<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Bom\StripBomComponent;
use Defibrion\Samenstellingen\Application\Bom\StripBomComponentHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bom:strip-component',
    description: 'Verwijder een BOM-component (bv. een out-of-stock stickerset) uit alle samenstellingen in AFAS én uit group_base_items. Default dry-run.',
)]
final class StripBomComponentCommand extends Command
{
    public function __construct(private readonly StripBomComponentHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode van de te-strippen BOM-component (bv. 81611).')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt DELETE in AFAS + group_base_items. Default = dry-run.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N AFAS-regels.', '0')
            ->addOption(
                'only-with',
                null,
                InputOption::VALUE_REQUIRED,
                'Komma-gescheiden itemcodes: strip alleen uit samenstellingen die óók een van deze componenten bevatten '
                . '(bv. 81111 strippen waar een anderstalige stickerset 81211,81311,81411,81511,81611 zit). '
                . 'De tool-side DELETE volgt dezelfde scope.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('itemcode');
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');
        $onlyWithRaw = $input->getOption('only-with');
        $onlyWith = is_string($onlyWithRaw) && trim($onlyWithRaw) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $onlyWithRaw)), static fn (string $c): bool => $c !== ''))
            : [];

        $result = ($this->handler)(new StripBomComponent(
            bomItemcode: $itemcode,
            apply: $apply,
            limit: $limit > 0 ? $limit : null,
            onlyWith: $onlyWith,
        ));
        if ($onlyWith !== []) {
            $io->writeln(sprintf(
                '<info>%d regel(s) overgeslagen</info>: samenstelling bevat geen van %s.',
                $result->skippedCount,
                implode(', ', $onlyWith),
            ));
        }

        if ($result->unsafeLines !== []) {
            $io->warning(sprintf(
                '%d regel(s) ONVEILIG overgeslagen: PrSe niet uniek binnen de samenstelling (AFAS matcht de delete op PrSe alléén). Herstel de volgorde in AFAS en draai opnieuw.',
                count($result->unsafeLines),
            ));
            $io->table(
                ['Samenstelling', 'VaIt', 'PrSe'],
                array_map(static fn ($l) => [$l->samenstellingItemcode, $l->vaIt, (string) $l->prSe], $result->unsafeLines),
            );
        }
        if ($result->plannedLines === []) {
            $io->success(sprintf('Geen AFAS-samenstellingen bevatten %s.', $itemcode));

            return Command::SUCCESS;
        }

        $io->section(sprintf(
            '%d AFAS-regel(s) — %s',
            count($result->plannedLines),
            $apply ? 'APPLY' : 'dry-run',
        ));
        $rows = [];
        foreach ($result->plannedLines as $line) {
            $rows[] = [$line->samenstellingItemcode, $line->vaIt, (string) $line->prSe];
        }
        $io->table(['Samenstelling', 'VaIt', 'PrSe'], $rows);

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen mutaties. Run met --apply.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d group_base_items-rij(en) verwijderd uit lokale tool.</info>',
            $result->toolRowsDeleted,
        ));
        $io->writeln(sprintf(
            '<info>%d AFAS-regel(s) gestript</info>, <comment>%d gefaald</comment>.',
            $result->appliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/strip-component-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['samenstelling', 'bom_itemcode', 'prse', 'vait', 'error'], ',', '"', '\\');
                foreach ($result->failures as $f) {
                    $line = $f['line'];
                    fputcsv($fh, [
                        $line->samenstellingItemcode,
                        $line->bomItemcode,
                        $line->prSe,
                        $line->vaIt,
                        $f['error'],
                    ], ',', '"', '\\');
                }
                fclose($fh);
                $io->writeln(sprintf('Failures gelogd naar <comment>%s</comment>.', $csv));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
