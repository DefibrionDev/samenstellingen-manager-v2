<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:list',
    description: 'Toon alle geregistreerde groepen met family-head, naam en aantal bases.',
)]
final class ListGroupsCommand extends Command
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly GroupBaseRepository $bases,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = $this->groups->findAll();

        if ($groups === []) {
            $io->writeln('<comment>Geen groepen geregistreerd.</comment>');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $group) {
            $rows[] = [
                $group->familyHeadItemcode,
                $group->name,
                $group->modelNameNl ?? '—',
                (string) count($this->bases->findAllForGroup($group->familyHeadItemcode)),
            ];
        }
        $io->table(['Family-head', 'Naam', 'Model (NL)', 'Bases'], $rows);
        $io->writeln(sprintf('<info>%d groep(en).</info>', count($groups)));

        return Command::SUCCESS;
    }
}
