<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'afas:list-duplicates',
    description: 'Toon alle AFAS-samenstellingen die door duplicate-detectie als kopie zijn gemarkeerd (zelfde BOM als canonical).',
)]
final class AfasListDuplicatesCommand extends Command
{
    public function __construct(private readonly AfasSamenstellingenRepository $repository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $duplicates = $this->repository->findAllDuplicates();

        if ($duplicates === []) {
            $io->success('Geen duplicates in de huidige AFAS-snapshot.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($duplicates as $duplicate) {
            $rows[] = [
                $duplicate->duplicateOfItemcode ?? '?',
                $duplicate->itemcode,
                $duplicate->name,
            ];
        }
        $io->table(['Canonical', 'Duplicate', 'Naam van duplicate'], $rows);
        $io->writeln(sprintf('Totaal: %d duplicates.', count($duplicates)));

        return Command::SUCCESS;
    }
}
