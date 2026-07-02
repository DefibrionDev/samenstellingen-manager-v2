<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariants;
use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariantsHandler;
use Defibrion\Samenstellingen\Application\Audit\NoMatchVariantRow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:no-match',
    description: 'Lijst álle no_match-varianten met per rij de actie (aanmaakbaar / bestaat al met afwijkende BOM / BOM bestaat elders / base niet gematcht). Met --csv als export.',
)]
final class AuditNoMatchVariantsCommand extends Command
{
    public function __construct(private readonly ListNoMatchVariantsHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Schrijf de rijen (na filtering) als CSV naar dit pad.')
            ->addOption('actie', null, InputOption::VALUE_REQUIRED, sprintf(
                'Filter op actie: %s.',
                implode(' | ', [
                    NoMatchVariantRow::ACTIE_AANMAAKBAAR,
                    NoMatchVariantRow::ACTIE_BESTAAT_AL,
                    NoMatchVariantRow::ACTIE_BOM_ELDERS,
                    NoMatchVariantRow::ACTIE_BASE_NIET_GEMATCHT,
                ]),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $actieFilter = $input->getOption('actie');
        $csvPath = $input->getOption('csv');

        $rows = ($this->handler)(new ListNoMatchVariants());
        if (is_string($actieFilter) && $actieFilter !== '') {
            $rows = array_values(array_filter($rows, static fn (NoMatchVariantRow $r): bool => $r->actie === $actieFilter));
        }

        if ($rows === []) {
            $io->success('Geen no_match-varianten gevonden.');

            return Command::SUCCESS;
        }

        $perActie = [];
        $tableRows = [];
        foreach ($rows as $row) {
            $perActie[$row->actie] = ($perActie[$row->actie] ?? 0) + 1;
            $tableRows[] = [
                $row->groep,
                $row->baseNaam,
                $row->accessoireItemcode !== '' ? $row->accessoireItemcode : '(geen)',
                implode(', ', $row->verwachteBom),
                $row->bestaandeAfasItemcode ?? '—',
                $row->ontbrekendeItemcodes === [] ? '—' : implode(', ', $row->ontbrekendeItemcodes),
                $row->extraItemcodes === [] ? '—' : implode(', ', $row->extraItemcodes),
                $row->actie,
            ];
        }
        $io->table(
            ['groep', 'base', 'accessoire', 'verwachte_bom', 'bestaat_in_afas', 'mist', 'teveel', 'actie'],
            $tableRows,
        );

        ksort($perActie);
        $io->note(sprintf(
            '%d no_match-variant(en): %s.',
            count($rows),
            implode(', ', array_map(
                static fn (string $actie, int $n): string => sprintf('%d × %s', $n, $actie),
                array_keys($perActie),
                $perActie,
            )),
        ));

        if (is_string($csvPath) && $csvPath !== '') {
            $fh = fopen($csvPath, 'w');
            if ($fh === false) {
                $io->error(sprintf("Kan '%s' niet openen om te schrijven.", $csvPath));

                return Command::FAILURE;
            }
            fputcsv($fh, ['groep', 'family_head', 'base', 'base_afas_sku', 'accessoire', 'accessoire_label', 'verwachte_bom', 'verwachte_itemcode', 'bestaat_in_afas', 'exacte_bom_elders', 'mist', 'teveel', 'actie']);
            foreach ($rows as $row) {
                fputcsv($fh, [
                    $row->groep,
                    $row->familyHead,
                    $row->baseNaam,
                    $row->baseAfasSku,
                    $row->accessoireItemcode,
                    $row->accessoireLabel,
                    implode('|', $row->verwachteBom),
                    $row->verwachteItemcode,
                    $row->bestaandeAfasItemcode ?? '',
                    $row->exacteBomMatchItemcode ?? '',
                    implode('|', $row->ontbrekendeItemcodes),
                    implode('|', $row->extraItemcodes),
                    $row->actie,
                ]);
            }
            fclose($fh);
            $io->writeln(sprintf('<info>%d rij(en) geëxporteerd naar %s.</info>', count($rows), $csvPath));
        }

        return Command::SUCCESS;
    }
}
