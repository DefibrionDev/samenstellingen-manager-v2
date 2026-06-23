<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariants;
use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariantsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:no-match',
    description: 'Lijst álle no_match-varianten: verwachte BOM-itemcodes + of er al een AFAS-compositie met de verwachte itemcode (of exact deze BOM) bestaat.',
)]
final class AuditNoMatchVariantsCommand extends Command
{
    public function __construct(private readonly ListNoMatchVariantsHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new ListNoMatchVariants());

        if ($rows === []) {
            $io->success('Geen no_match-varianten gevonden.');

            return Command::SUCCESS;
        }

        $withExactDuplicate = 0;
        $tableRows = [];
        foreach ($rows as $row) {
            if ($row->exacteBomMatchItemcode !== null) {
                $withExactDuplicate++;
            }
            $tableRows[] = [
                $row->groep,
                $row->baseNaam,
                $row->accessoireItemcode !== '' ? $row->accessoireItemcode : '(geen)',
                implode(', ', $row->verwachteBom),
                $row->bestaandeAfasItemcode ?? '—',
                $row->ontbrekendeItemcodes === [] ? '—' : implode(', ', $row->ontbrekendeItemcodes),
                $row->extraItemcodes === [] ? '—' : implode(', ', $row->extraItemcodes),
            ];
        }
        $io->table(
            ['groep', 'base', 'accessoire', 'verwachte_bom', 'bestaat_in_afas', 'mist', 'teveel'],
            $tableRows,
        );
        $io->note(sprintf(
            '%d no_match-variant(en). Bij een gevulde "bestaat_in_afas" bestaat de compositie al maar matchte niet: '
            . '"mist" = itemcodes die in AFAS ontbreken, "teveel" = itemcodes die er teveel in zitten. '
            . 'Rijen zonder "bestaat_in_afas" ontbreken volledig in AFAS.',
            count($rows),
        ));
        if ($withExactDuplicate > 0) {
            $io->note(sprintf(
                '%d variant(en) hebben elders al een compositie met exact deze BOM (mogelijk een duplicaat).',
                $withExactDuplicate,
            ));
        }

        return Command::SUCCESS;
    }
}
