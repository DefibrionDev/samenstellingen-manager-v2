<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditVariantParent;
use Defibrion\Samenstellingen\Application\Audit\VariantParentAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:variant-parent',
    description: 'Detecteer matched accessoire-variants waarvan AFAS-Itemcode_Parent niet naar de family-head wijst.',
)]
final class AuditVariantParentCommand extends Command
{
    public function __construct(private readonly VariantParentAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditVariantParent());

        if ($rows === []) {
            $io->success('Alle matched accessoire-variants wijzen naar hun family-head (of zijn afwezig in de AFAS-snapshot).');

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->afasItemcode,
                $row->currentParent ?? '(leeg)',
                $row->expectedParent,
                $row->groupName,
            ];
        }
        $io->table(['variant', 'huidige_parent', 'verwacht', 'groep'], $tableRows);
        $io->note(sprintf('%d variant(s) met drift. Fix lege rijen met `variant:fix-parent --apply`. Afwijkend gevulde rijen worden NIET overschreven.', count($rows)));

        return Command::SUCCESS;
    }
}
