<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroup;
use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:add-accessoire',
    description: 'Koppel een bestaande catalogus-accessoire aan een groep.',
)]
final class AddAccessoireCommand extends Command
{
    public function __construct(private readonly AddAccessoireToGroupHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'family-head-itemcode',
                InputArgument::REQUIRED,
                'AFAS family-head itemcode van de groep',
            )
            ->addArgument(
                'accessoire-itemcode',
                InputArgument::REQUIRED,
                'Itemcode van een reeds geregistreerde accessoire',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $familyHead = (string) $input->getArgument('family-head-itemcode');
        $itemcode = (string) $input->getArgument('accessoire-itemcode');

        try {
            ($this->handler)(new AddAccessoireToGroup($familyHead, $itemcode));
        } catch (GroupNotFoundException | AccessoireNotFoundException | AccessoireAlreadyLinkedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Accessoire '%s' gekoppeld aan groep %s.", $itemcode, $familyHead));

        return Command::SUCCESS;
    }
}
