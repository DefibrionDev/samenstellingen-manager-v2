<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditBaseParent;
use Defibrion\Samenstellingen\Application\Audit\BaseParentAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:base-parent',
    description: 'Detecteer non-head bases waarvan AFAS-Itemcode_Parent niet naar de family-head wijst. Defibrion-conventie: base.parent = family_head.',
)]
final class AuditBaseParentCommand extends Command
{
    public function __construct(private readonly BaseParentAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditBaseParent());

        if ($rows === []) {
            $io->success('Alle non-head bases wijzen naar hun family-head (of zijn afwezig in de AFAS-snapshot).');

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->afasItemcode,
                $row->currentParent ?? '(leeg)',
                $row->expectedParent,
                $row->groupName,
                $row->languageCode,
            ];
        }
        $io->table(['base', 'huidige_parent', 'verwacht', 'groep', 'taal'], $tableRows);
        $io->note(sprintf('%d non-head base(s) met drift. Fix lege rijen met `base:fix-parent --apply`. Afwijkend gevulde rijen worden NIET overschreven.', count($rows)));

        return Command::SUCCESS;
    }
}
