<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseToGroup;
use Defibrion\Samenstellingen\Application\Group\AddBaseToGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:add-base',
    description: 'Voeg een basissamenstelling (taal-specifieke AED) toe aan een bestaande groep.',
)]
final class AddBaseCommand extends Command
{
    public function __construct(private readonly AddBaseToGroupHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('group', InputArgument::REQUIRED, 'De groepsnaam')
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode van de basissamenstelling')
            ->addArgument('language-code', InputArgument::REQUIRED, 'Taal-label (bv. "NL", "DE", "FR")')
            ->addArgument('name', InputArgument::REQUIRED, 'AFAS-naam van de basissamenstelling');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $base = ($this->handler)(new AddBaseToGroup(
                (string) $input->getArgument('group'),
                (string) $input->getArgument('itemcode'),
                (string) $input->getArgument('language-code'),
                (string) $input->getArgument('name'),
            ));
        } catch (GroupNotFoundException | BaseAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Base '%s' (%s) toegevoegd aan groep '%s'.",
            $base->itemcode,
            $base->languageCode,
            (string) $input->getArgument('group'),
        ));

        return Command::SUCCESS;
    }
}
