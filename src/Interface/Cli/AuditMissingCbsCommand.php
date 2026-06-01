<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditMissingCbs;
use Defibrion\Samenstellingen\Application\Audit\MissingCbsAuditHandler;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:missing-cbs',
    description: 'Toon alle AFAS-samenstellingen waar CBS-goederencode leeg is. Blokkeren `variants:fix-missing --apply` als ze als referentie-variant gekozen worden.',
)]
final class AuditMissingCbsCommand extends Command
{
    public function __construct(private readonly MissingCbsAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Schrijf de tabel als CSV naar dit pad i.p.v. naar stdout.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditMissingCbs());

        if ($rows === []) {
            $io->success('Alle AFAS-samenstellingen hebben een CBS-goederencode.');

            return Command::SUCCESS;
        }

        $csvOption = $input->getOption('csv');
        if (is_string($csvOption) && $csvOption !== '') {
            $this->writeCsv($csvOption, $rows);
            $io->success(sprintf('%d samenstelling(en) zonder CBS geëxporteerd naar %s.', count($rows), $csvOption));

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->itemcode,
                mb_strlen($row->name) > 60 ? mb_substr($row->name, 0, 59) . '…' : $row->name,
                $row->itemcodeParent ?? '',
            ];
        }
        $io->table(['Itemcode', 'Naam', 'Itemcode_Parent'], $tableRows);
        $io->writeln(sprintf('<info>%d samenstelling(en) zonder CBS-goederencode.</info>', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @param list<\Defibrion\Samenstellingen\Application\Audit\MissingCbsRow> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException(sprintf('Kan CSV-bestand niet openen voor schrijven: %s', $path));
        }
        fputcsv($fh, ['itemcode', 'name', 'itemcode_parent'], ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, [$row->itemcode, $row->name, $row->itemcodeParent ?? ''], ',', '"', '\\');
        }
        fclose($fh);
    }
}
