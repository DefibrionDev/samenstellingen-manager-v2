<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditNames;
use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:names',
    description: 'Vergelijk de werkelijke AFAS-naam van elke gematchte samenstelling met de canonieke template (PLAN.md §9.1). Rapporteer drift.',
)]
final class AuditNamesCommand extends Command
{
    public function __construct(private readonly NameAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk output tot N drift-rijen', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditNames());

        if ($rows === []) {
            $io->success('Geen naam-drift gevonden. Alle gematchte AFAS-samenstellingen volgen de template.');

            return Command::SUCCESS;
        }

        $limit = (int) $input->getOption('limit');
        $shown = $limit > 0 ? array_slice($rows, 0, $limit) : $rows;

        $tableRows = [];
        foreach ($shown as $row) {
            $tableRows[] = [
                $row->afasItemcode,
                $row->languageCode,
                $row->accessoireItemcode ?? '—',
                $row->expected,
                $row->actual,
            ];
        }
        $io->section(sprintf('%d drift-rij(en) gevonden', count($rows)));
        $io->table(['SKU', 'Taal', 'Accessoire', 'Verwacht', 'Werkelijk'], $tableRows);

        if ($limit > 0 && count($rows) > $limit) {
            $io->writeln(sprintf('<comment>%d rij(en) niet getoond (zie /name-drift in UI of run zonder --limit).</comment>', count($rows) - $limit));
        }

        return Command::FAILURE;
    }
}
