<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixVariantParent;
use Defibrion\Samenstellingen\Application\Fix\FixVariantParentHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'variant:fix-parent',
    description: 'Vul AFAS Itemcode_Parent op matched accessoire-variants waar het veld leeg is met de family-head. Default dry-run.',
)]
final class FixVariantParentCommand extends Command
{
    public function __construct(private readonly FixVariantParentHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Echt PUT naar AFAS. Default = dry-run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $result = ($this->handler)(new FixVariantParent(apply: $apply));

        if ($result->plans === [] && $result->skippedFilled === []) {
            $io->success('Niets te doen — alle matched accessoire-variants wijzen naar hun family-head.');

            return Command::SUCCESS;
        }

        if ($result->skippedFilled !== []) {
            $io->note(sprintf('%d variant(s) met afwijkende parent worden NIET overschreven:', count($result->skippedFilled)));
            $rows = [];
            foreach ($result->skippedFilled as $row) {
                $rows[] = [$row->afasItemcode, $row->currentParent, $row->expectedParent, $row->groupName];
            }
            $io->table(['variant', 'huidige_parent', 'verwacht', 'groep'], $rows);
        }

        if ($result->plans === []) {
            $io->success('Geen lege variant-parents — niets om te vullen.');

            return Command::SUCCESS;
        }

        $modeLabel = $apply ? 'APPLY' : 'dry-run';
        $io->section(sprintf('%d plan(s) — %s', count($result->plans), $modeLabel));
        $rows = [];
        foreach ($result->plans as $plan) {
            $rows[] = [$plan->afasItemcode, '(leeg) → ' . $plan->expectedParent, $plan->groupName];
        }
        $io->table(['variant', 'mutatie', 'groep'], $rows);

        if (!$apply) {
            $io->note('Dry-run — geen AFAS-mutaties. Run met --apply om te schrijven.');

            return Command::SUCCESS;
        }

        if ($result->failures !== []) {
            $csvPath = sys_get_temp_dir() . '/variant-parent-fix-failures-' . date('Y-m-d-His') . '.csv';
            $fh = fopen($csvPath, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['variant', 'error']);
                foreach ($result->failures as $f) {
                    fputcsv($fh, [$f['plan']->afasItemcode, $f['error']]);
                }
                fclose($fh);
                $io->warning(sprintf('Failures gelogd naar %s', $csvPath));
            }
        }
        $io->success(sprintf('%d toegepast, %d gefaald.', $result->applied, count($result->failures)));

        return Command::SUCCESS;
    }
}
