<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Fix\FixNames;
use Defibrion\Samenstellingen\Application\Fix\FixNamesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'names:fix-drift',
    description: 'Schrijf canonical AED-pakket-namen naar AFAS via FbItemArticle.Ds. Default dry-run.',
)]
final class FixNamesCommand extends Command
{
    public function __construct(private readonly FixNamesHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt PUT naar AFAS. Default = dry-run.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N drift-rijen.', '0')
            ->addOption('base', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Alleen drift van deze base-itemcode(s) fixen (herhaalbaar).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');
        /** @var list<string> $baseItemcodes */
        $baseItemcodes = $input->getOption('base');

        $result = ($this->handler)(new FixNames(
            apply: $apply,
            limit: $limit > 0 ? $limit : null,
            baseItemcodes: $baseItemcodes === [] ? null : $baseItemcodes,
        ));

        if ($result->plans === []) {
            $io->success('Geen name-drift te fixen — alle samenstellingen kloppen al met canonical.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($result->plans as $p) {
            $rows[] = [
                $p->afasItemcode,
                $this->ellipsize($p->currentName, 60),
                $this->ellipsize($p->targetName, 60),
            ];
        }
        $io->section(sprintf('%d name-drift-rij(en) — %s', count($result->plans), $apply ? 'APPLY' : 'dry-run'));
        $io->table(['Itemcode', 'Huidig', 'Canonical'], $rows);

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
            $csv = sprintf('tmp/fix-names-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['itemcode', 'current', 'target', 'error']);
                foreach ($result->failures as $f) {
                    fputcsv($fh, [$f['plan']->afasItemcode, $f['plan']->currentName, $f['plan']->targetName, $f['error']]);
                }
                fclose($fh);
                $io->writeln(sprintf('Failures gelogd naar <comment>%s</comment>.', $csv));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function ellipsize(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
    }
}
