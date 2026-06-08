<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixFamilyHeadParent;
use Defibrion\Samenstellingen\Application\Fix\FixFamilyHeadParentHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'family-head:fix-parent',
    description: 'Vul AFAS Itemcode_Parent op family-heads waar het veld leeg is met de itemcode zelf. Default dry-run.',
)]
final class FixFamilyHeadParentCommand extends Command
{
    public function __construct(private readonly FixFamilyHeadParentHandler $handler)
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

        $result = ($this->handler)(new FixFamilyHeadParent(apply: $apply));

        if ($result->plans === [] && $result->skippedFilled === []) {
            $io->success('Niets te doen — alle family-heads hebben self-parent (of zijn afwezig in de snapshot).');

            return Command::SUCCESS;
        }

        if ($result->skippedFilled !== []) {
            $io->note(sprintf('%d family-head(s) met afwijkende parent worden NIET overschreven:', count($result->skippedFilled)));
            $rows = [];
            foreach ($result->skippedFilled as $row) {
                $rows[] = [$row->familyHead, $row->currentParent, $row->groupName];
            }
            $io->table(['family_head', 'huidige_parent', 'groep'], $rows);
        }

        if ($result->plans === []) {
            $io->success('Geen lege family-head-parents — niets om te vullen.');

            return Command::SUCCESS;
        }

        $modeLabel = $apply ? 'APPLY' : 'dry-run';
        $io->section(sprintf('%d plan(s) — %s', count($result->plans), $modeLabel));
        $rows = [];
        foreach ($result->plans as $plan) {
            $rows[] = [$plan->familyHead, '(leeg) → ' . $plan->expectedParent, $plan->groupName];
        }
        $io->table(['family_head', 'mutatie', 'groep'], $rows);

        if (!$apply) {
            $io->note('Dry-run — geen AFAS-mutaties. Run met --apply om te schrijven.');

            return Command::SUCCESS;
        }

        if ($result->failures !== []) {
            $csvPath = sys_get_temp_dir() . '/family-head-fix-failures-' . date('Y-m-d-His') . '.csv';
            $fh = fopen($csvPath, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['family_head', 'error']);
                foreach ($result->failures as $f) {
                    fputcsv($fh, [$f['plan']->familyHead, $f['error']]);
                }
                fclose($fh);
                $io->warning(sprintf('Failures gelogd naar %s', $csvPath));
            }
        }
        $io->success(sprintf('%d toegepast, %d gefaald.', $result->applied, count($result->failures)));

        return Command::SUCCESS;
    }
}
