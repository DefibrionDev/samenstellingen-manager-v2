<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Bom\RestoreStickers;
use Defibrion\Samenstellingen\Application\Bom\RestoreStickersHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'stickers:restore',
    description: 'Voeg ontbrekende stickersets toe aan bases + bijbehorende AFAS-samenstellingen, op basis van StickerPolicy en base-taal. Default dry-run.',
)]
final class RestoreStickersCommand extends Command
{
    public function __construct(private readonly RestoreStickersHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Echt INSERT in tool + AFAS. Default = dry-run.')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'Beperk tot bases met deze taal-code (bv. EN voor 81611).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk tot N AFAS-inserts.', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = (int) $input->getOption('limit');
        $langOption = $input->getOption('language');
        $language = is_string($langOption) && $langOption !== '' ? $langOption : null;

        $result = ($this->handler)(new RestoreStickers(
            apply: $apply,
            languageCode: $language,
            limit: $limit > 0 ? $limit : null,
        ));

        if ($result->toolInserts === [] && $result->afasPlans === []) {
            $io->success('Geen ontbrekende stickersets.');

            return Command::SUCCESS;
        }

        if ($result->toolInserts !== []) {
            $io->section(sprintf('%d tool-rij(en) (group_base_items)', count($result->toolInserts)));
            $rows = [];
            foreach ($result->toolInserts as $i) {
                $rows[] = [(string) $i['baseId'], $i['baseAfasItemcode'] ?? '', $i['languageCode'], $i['sticker']];
            }
            $io->table(['Base id', 'AFAS itemcode', 'Taal', 'Sticker'], $rows);
        }

        if ($result->afasPlans !== []) {
            $io->section(sprintf(
                '%d AFAS-regel(s) — %s',
                count($result->afasPlans),
                $apply ? 'APPLY' : 'dry-run',
            ));
            $rows = [];
            foreach ($result->afasPlans as $plan) {
                $rows[] = [$plan->samenstellingItemcode, $plan->bomItemcode, $plan->vaIt, (string) $plan->prSe];
            }
            $io->table(['Samenstelling', 'Sticker', 'VaIt', 'PrSe'], $rows);
        }

        if (!$apply) {
            $io->writeln('<comment>Dry-run — geen mutaties. Run met --apply.</comment>');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '<info>%d tool-rij(en) ingevoegd</info>, <info>%d AFAS-regel(s) ingevoegd</info>, <comment>%d gefaald</comment>.',
            $result->toolInsertedCount,
            $result->afasAppliedCount,
            count($result->failures),
        ));

        if ($result->failures !== []) {
            $csv = sprintf('tmp/restore-stickers-%s.csv', date('Y-m-d-His'));
            $fh = fopen($csv, 'w');
            if ($fh !== false) {
                fputcsv($fh, ['samenstelling', 'bom_itemcode', 'prse', 'vait', 'error'], ',', '"', '\\');
                foreach ($result->failures as $f) {
                    $plan = $f['plan'];
                    fputcsv($fh, [
                        $plan->samenstellingItemcode,
                        $plan->bomItemcode,
                        $plan->prSe,
                        $plan->vaIt,
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
