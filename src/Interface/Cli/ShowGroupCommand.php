<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\GroupOverview;
use Defibrion\Samenstellingen\Application\Group\GroupVariantWithBom;
use Defibrion\Samenstellingen\Application\Group\ShowGroup;
use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:show',
    description: 'Toon de details van een groep (lookup via family-head itemcode), inclusief bases, accessoires en varianten met verwachte BOM.',
)]
final class ShowGroupCommand extends Command
{
    public function __construct(private readonly ShowGroupHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'family-head-itemcode',
            InputArgument::REQUIRED,
            'AFAS family-head itemcode van de groep (bv. 52112)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('family-head-itemcode');

        try {
            $overview = ($this->handler)(new ShowGroup($itemcode));
        } catch (GroupNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->renderOverview($io, $overview);

        return Command::SUCCESS;
    }

    private function renderOverview(SymfonyStyle $io, GroupOverview $overview): void
    {
        $io->horizontalTable(
            ['Naam', 'Family-head itemcode'],
            [[$overview->group->name, $overview->group->familyHeadItemcode]],
        );

        $io->section('Bases');
        if ($overview->bases === []) {
            $io->writeln('(geen bases)');
        } else {
            $rows = [];
            foreach ($overview->bases as $base) {
                $rows[] = [
                    (string) ($base->id ?? '?'),
                    $base->languageCode ?? '—',
                    $base->name,
                ];
            }
            $io->table(['ID', 'Taal', 'Naam'], $rows);
        }

        $io->section('Accessoires (gekoppeld aan deze groep)');
        if ($overview->accessoires === []) {
            $io->writeln('(geen accessoires)');
        } else {
            $rows = [];
            foreach ($overview->accessoires as $accessoire) {
                $rows[] = [$accessoire->itemcode, $accessoire->label];
            }
            $io->table(['Itemcode', 'Label'], $rows);
        }

        $io->section('Varianten met verwachte BOM');
        if ($overview->variants === []) {
            $io->writeln('(nog geen varianten)');

            return;
        }

        foreach ($overview->variants as $i => $variantWithBom) {
            $this->renderVariant($io, $i + 1, $variantWithBom);
        }
    }

    private function renderVariant(SymfonyStyle $io, int $index, GroupVariantWithBom $variantWithBom): void
    {
        $variant = $variantWithBom->variant;
        $statusLabel = match ($variant->afasStatus) {
            'matched' => sprintf('<info>✓ matched</info> (%s)', $variant->afasSamenstellingItemcode ?? '?'),
            'no_match' => '<comment>✗ no_match</comment>',
            default => '<fg=gray>— niet gecheckt</>',
        };
        $header = sprintf(
            '#%d — Base #%d (%s)%s   |   AFAS: %s',
            $index,
            $variant->baseId,
            $variant->baseName,
            $variant->accessoireItemcode !== null
                ? sprintf(' + %s (%s)', $variant->accessoireItemcode, $variant->accessoireLabel ?? '')
                : '',
            $statusLabel,
        );
        $io->writeln($header);

        if ($variantWithBom->bom === []) {
            $io->writeln('  (lege BOM — voeg eerst base-items toe)');
            $io->newLine();

            return;
        }

        $rows = [];
        foreach ($variantWithBom->bom as $bomItem) {
            $rows[] = [$bomItem->itemcode, $bomItem->name];
        }
        $io->table(['Itemcode', 'Naam'], $rows);
    }
}
