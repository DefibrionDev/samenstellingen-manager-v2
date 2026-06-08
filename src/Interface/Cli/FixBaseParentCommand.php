<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixBaseParent;
use Defibrion\Samenstellingen\Application\Fix\FixBaseParentHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'base:fix-parent',
    description: 'Vul AFAS Itemcode_Parent op non-head bases waar het veld leeg is met de family-head. Default dry-run.',
)]
final class FixBaseParentCommand extends Command
{
    public function __construct(private readonly FixBaseParentHandler $handler)
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

        $result = ($this->handler)(new FixBaseParent(apply: $apply));

        if ($result->plans === [] && $result->skippedFilled === []) {
            $io->success('Niets te doen — alle non-head bases wijzen naar hun family-head (of zijn afwezig in de snapshot).');

            return Command::SUCCESS;
        }

        if ($result->skippedFilled !== []) {
            $io->note(sprintf('%d base(s) met afwijkende parent worden NIET overschreven:', count($result->skippedFilled)));
            $rows = [];
            foreach ($result->skippedFilled as $row) {
                $rows[] = [$row->afasItemcode, $row->currentParent, $row->expectedParent, $row->groupName];
            }
            $io->table(['base', 'huidige_parent', 'verwacht', 'groep'], $rows);
        }

        if ($result->plans === []) {
            $io->success('Geen lege base-parents — niets om te vullen.');

            return Command::SUCCESS;
        }

        $modeLabel = $apply ? 'APPLY' : 'dry-run';
        $io->section(sprintf('%d plan(s) — %s', count($result->plans), $modeLabel));
        $rows = [];
        foreach ($result->plans as $plan) {
            $rows[] = [$plan->afasItemcode, '(leeg) → ' . $plan->expectedParent, $plan->groupName, $plan->languageCode];
        }
        $io->table(['base', 'mutatie', 'groep', 'taal'], $rows);

        if (!$apply) {
            $io->note('Dry-run — geen AFAS-mutaties. Run met --apply om te schrijven.');

            return Command::SUCCESS;
        }

        if ($result->failures !== []) {
            $csvPath = sys_get_temp_dir() . '/base-parent-fix-failures-' . date('Y-m-d-His') . '.csv';
            $fh = fopen($csvPath, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['base', 'error']);
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
