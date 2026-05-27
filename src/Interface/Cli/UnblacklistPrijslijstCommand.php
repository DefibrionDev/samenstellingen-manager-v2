<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotBlacklistedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricelist:unblacklist',
    description: 'Verwijder een prijslijst-ID van de blacklist.',
)]
final class UnblacklistPrijslijstCommand extends Command
{
    public function __construct(private readonly PrijslijstBlacklistRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Prijslijst-ID om van de blacklist te halen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');

        try {
            $this->repository->remove($id);
        } catch (PrijslijstNotBlacklistedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Prijslijst '%s' verwijderd van de blacklist.", $id));

        return Command::SUCCESS;
    }
}
