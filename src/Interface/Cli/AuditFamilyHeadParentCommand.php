<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditFamilyHeadParent;
use Defibrion\Samenstellingen\Application\Audit\FamilyHeadParentAuditHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:family-head-parent',
    description: 'Detecteer family-heads waarvan AFAS-Itemcode_Parent niet naar zichzelf wijst. Defibrion-conventie: head.parent = head.itemcode.',
)]
final class AuditFamilyHeadParentCommand extends Command
{
    public function __construct(private readonly FamilyHeadParentAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditFamilyHeadParent());

        if ($rows === []) {
            $io->success('Alle family-heads in onze groepen wijzen naar zichzelf (of zijn afwezig in de AFAS-snapshot).');

            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->familyHead,
                $row->currentParent ?? '(leeg)',
                $row->expectedParent,
                $row->groupName,
            ];
        }
        $io->table(['family_head', 'huidige_parent', 'verwacht', 'groep'], $tableRows);
        $io->note(sprintf('%d family-head(s) met drift. Fix lege rijen met `family-head:fix-parent --apply`. Afwijkend gevulde rijen worden NIET overschreven.', count($rows)));

        return Command::SUCCESS;
    }
}
