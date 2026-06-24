<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Publications\SyncPublications;
use Defibrion\Samenstellingen\Application\Publications\SyncPublicationsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'publications:sync',
    description: 'Synchroniseer publicatie-state (per base × website) naar AFAS. PUT FbComposition met free-field flags op base + accessoire-varianten. Default dry-run.',
)]
final class SyncPublicationsCommand extends Command
{
    public function __construct(private readonly SyncPublicationsHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt PUT naar AFAS. Default = dry-run.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N plans.', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');

        $result = ($this->handler)(new SyncPublications(
            apply: $apply,
            limit: $limit > 0 ? $limit : null,
        ));

        if ($result->onlineNotAssigned !== []) {
            $io->warning(sprintf(
                '%d itemcode×website staat online in AFAS maar is niet toegekend in de tool — NIET uitgezet, alleen gemeld:',
                count($result->onlineNotAssigned),
            ));
            $rows = [];
            foreach (array_slice($result->onlineNotAssigned, 0, 40) as $r) {
                $rows[] = [$r->afasItemcode, $r->baseAfasItemcode, $r->websiteName];
            }
            $io->table(['itemcode', 'base', 'website'], $rows);
            if (count($result->onlineNotAssigned) > 40) {
                $io->writeln(sprintf('<comment>… +%d meer (zie de "online niet toegekend"-audit).</comment>', count($result->onlineNotAssigned) - 40));
            }
        }

        if ($result->plans === []) {
            if ($result->totalCandidates === 0) {
                $io->success('Geen plans — geen websites of geen bases met SKU.');
            } else {
                $io->success(sprintf(
                    'Niets te doen — alle %d itemcode(s) staan al goed in AFAS.',
                    $result->noopSkipped,
                ));
            }

            return Command::SUCCESS;
        }

        if ($result->noopSkipped > 0) {
            $io->writeln(sprintf(
                '<comment>%d itemcode(s) overgeslagen — AFAS-state matcht al.</comment>',
                $result->noopSkipped,
            ));
        }

        $rows = [];
        foreach ($result->plans as $p) {
            $trueFlags = array_filter($p->freeFieldFlags);
            $rows[] = [
                $p->afasItemcode,
                $p->baseAfasItemcode,
                (string) count($trueFlags),
                (string) (count($p->freeFieldFlags) - count($trueFlags)),
            ];
        }
        $io->section(sprintf('%d plan(s) — %s', count($result->plans), $apply ? 'APPLY' : 'dry-run'));
        $io->table(['Itemcode', 'Base', 'Flags true', 'Flags false'], $rows);

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen AFAS-mutaties. Run met --apply.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d toegepast</info>, <comment>%d gefaald</comment>.',
            $result->appliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/fix-publications-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['itemcode', 'base', 'error'], ',', '"', '\\');
                foreach ($result->failures as $f) {
                    fputcsv($fh, [$f['plan']->afasItemcode, $f['plan']->baseAfasItemcode, $f['error']], ',', '"', '\\');
                }
                fclose($fh);
                $io->writeln(sprintf('Failures gelogd naar <comment>%s</comment>.', $csv));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
