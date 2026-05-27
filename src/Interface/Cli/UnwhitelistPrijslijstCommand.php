<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotWhitelistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricelist:unwhitelist',
    description: 'Verwijder een prijslijst-ID van de whitelist.',
)]
final class UnwhitelistPrijslijstCommand extends Command
{
    public function __construct(private readonly PrijslijstWhitelistRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Prijslijst-ID om van de whitelist te halen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');

        try {
            $this->repository->remove($id);
        } catch (PrijslijstNotWhitelistedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Prijslijst '%s' verwijderd van de whitelist.", $id));

        return Command::SUCCESS;
    }
}
