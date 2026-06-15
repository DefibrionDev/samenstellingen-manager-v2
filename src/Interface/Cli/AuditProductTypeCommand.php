<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditProductType;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeAuditHandler;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:product-type',
    description: 'Detecteer samenstellingen met ontbrekende of afwijkende webshop-producttypes (Product_type 01/02).',
)]
final class AuditProductTypeCommand extends Command
{
    public function __construct(private readonly ProductTypeAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditProductType());

        if ($rows === []) {
            $io->success('Alle samenstellingen hebben producttype 01/02 gevuld en varianten komen overeen met hun base.');

            return Command::SUCCESS;
        }

        $tableRows = [];
        $baseEmpty = 0;
        $fixable = 0;
        $blocked = 0;
        foreach ($rows as $row) {
            $tableRows[] = [
                $row->afasItemcode,
                $this->label($row->issueType),
                $row->baseItemcode,
                $this->pair($row->current01, $row->current02),
                $this->pair($row->expected01, $row->expected02),
                $row->groupName,
            ];
            match ($row->issueType) {
                ProductTypeIssueType::BaseEmpty => $baseEmpty++,
                ProductTypeIssueType::VariantFixable => $fixable++,
                ProductTypeIssueType::VariantBlocked => $blocked++,
            };
        }

        $io->table(['itemcode', 'issue', 'base', 'huidig 01/02', 'verwacht 01/02', 'groep'], $tableRows);
        $io->note(sprintf(
            "%d issue(s): %d base-leeg, %d variant-fixbaar, %d variant-geblokkeerd.\n"
            . "Vul base-leeg-rijen handmatig in AFAS (Product_type___01_/02_).\n"
            . 'Fix varianten met `producttype:fix-variants --apply`.',
            count($rows),
            $baseEmpty,
            $fixable,
            $blocked,
        ));

        return Command::SUCCESS;
    }

    private function label(ProductTypeIssueType $type): string
    {
        return match ($type) {
            ProductTypeIssueType::BaseEmpty => 'base-leeg',
            ProductTypeIssueType::VariantFixable => 'variant-fixbaar',
            ProductTypeIssueType::VariantBlocked => 'variant-geblokkeerd',
        };
    }

    private function pair(?string $type01, ?string $type02): string
    {
        return sprintf('%s / %s', $type01 ?? '(leeg)', $type02 ?? '(leeg)');
    }
}
