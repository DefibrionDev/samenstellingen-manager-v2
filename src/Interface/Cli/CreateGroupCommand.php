<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\CreateGroup;
use Defibrion\Samenstellingen\Application\Group\CreateGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:create',
    description: 'Definieer een nieuwe groep met naam en family-head itemcode.',
)]
final class CreateGroupCommand extends Command
{
    public function __construct(private readonly CreateGroupHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'De groepsnaam (bv. "Reanibex 100 Semi-Auto")'
            )
            ->addArgument(
                'family-head-itemcode',
                InputArgument::REQUIRED,
                'AFAS itemcode dat de familie ankert (een willekeurige sibling van de groep)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $itemcode = (string) $input->getArgument('family-head-itemcode');

        try {
            $group = ($this->handler)(new CreateGroup($name, $itemcode));
        } catch (GroupAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Groep '%s' aangemaakt (family-head itemcode: %s).",
            $group->name,
            $group->familyHeadItemcode,
        ));

        return Command::SUCCESS;
    }
}
