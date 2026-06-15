<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixVariantProductType;
use Defibrion\Samenstellingen\Application\Fix\FixVariantProductTypeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'producttype:fix-variants',
    description: 'Trek webshop-producttypes van accessoire-varianten gelijk aan hun base-samenstelling. Default dry-run.',
)]
final class FixVariantProductTypeCommand extends Command
{
    public function __construct(private readonly FixVariantProductTypeHandler $handler)
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

        $result = ($this->handler)(new FixVariantProductType(apply: $apply));

        if ($result->skipped !== []) {
            $io->note(sprintf('%d samenstelling(en) vereisen handmatige AFAS-invoer (base-leeg of geblokkeerd):', count($result->skipped)));
            $rows = [];
            foreach ($result->skipped as $row) {
                $rows[] = [$row->afasItemcode, $row->issueType->value, $row->baseItemcode, $row->groupName];
            }
            $io->table(['itemcode', 'issue', 'base', 'groep'], $rows);
        }

        if ($result->plans === []) {
            $io->success('Geen auto-fixbare varianten — alle varianten komen overeen met hun base (of wachten op AFAS-invoer).');

            return Command::SUCCESS;
        }

        $modeLabel = $apply ? 'APPLY' : 'dry-run';
        $io->section(sprintf('%d plan(s) — %s', count($result->plans), $modeLabel));
        $rows = [];
        foreach ($result->plans as $plan) {
            $rows[] = [
                $plan->afasItemcode,
                sprintf('%s → %s', $this->pair($plan->current01, $plan->current02), $this->pair($plan->expected01, $plan->expected02)),
                $plan->groupName,
            ];
        }
        $io->table(['variant', 'mutatie (01/02)', 'groep'], $rows);

        if (!$apply) {
            $io->note('Dry-run — geen AFAS-mutaties. Run met --apply om te schrijven.');

            return Command::SUCCESS;
        }

        if ($result->failures !== []) {
            $csvPath = sys_get_temp_dir() . '/producttype-fix-failures-' . date('Y-m-d-His') . '.csv';
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

    private function pair(?string $type01, ?string $type02): string
    {
        return sprintf('%s/%s', $type01 ?? '(leeg)', $type02 ?? '(leeg)');
    }
}
