<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseItem;
use Defibrion\Samenstellingen\Application\Group\AddBaseItemHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:add-base-item',
    description: 'Voeg een item (AED, electrode, battery, stickerset, safeset, …) toe aan een base.',
)]
final class AddBaseItemCommand extends Command
{
    public function __construct(private readonly AddBaseItemHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('base-id', InputArgument::REQUIRED, 'Surrogate id van de base (zie group:show)')
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode van het item (bv. 50013)')
            ->addArgument('name', InputArgument::REQUIRED, 'AFAS-naam van het item');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseIdArg = (string) $input->getArgument('base-id');
        if (!ctype_digit($baseIdArg) || (int) $baseIdArg <= 0) {
            $io->error(sprintf("Ongeldige base-id '%s' — verwacht een positief geheel getal.", $baseIdArg));

            return Command::INVALID;
        }
        $baseId = (int) $baseIdArg;
        $itemcode = (string) $input->getArgument('itemcode');
        $name = (string) $input->getArgument('name');

        try {
            $item = ($this->handler)(new AddBaseItem($baseId, $itemcode, $name));
        } catch (BaseNotFoundException | BaseItemAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Item '%s' (%s) toegevoegd aan base #%d.",
            $item->itemcode,
            $item->name,
            $baseId,
        ));

        return Command::SUCCESS;
    }
}
