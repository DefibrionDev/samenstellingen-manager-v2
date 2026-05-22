<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\GroupOverview;
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
    description: 'Toon de details van een bestaande groep, inclusief bases en gekoppelde accessoires.',
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
            'name',
            InputArgument::REQUIRED,
            'De groepsnaam (bv. "Reanibex 100 Semi-Auto")'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        try {
            $overview = ($this->handler)(new ShowGroup($name));
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
                $rows[] = [$base->itemcode, $base->languageCode, $base->name];
            }
            $io->table(['Itemcode', 'Taal', 'Naam'], $rows);
        }

        $io->section('Accessoires');
        if ($overview->accessoires === []) {
            $io->writeln('(geen accessoires)');
        } else {
            $rows = [];
            foreach ($overview->accessoires as $accessoire) {
                $rows[] = [$accessoire->itemcode, $accessoire->label];
            }
            $io->table(['Itemcode', 'Label'], $rows);
        }
    }
}
