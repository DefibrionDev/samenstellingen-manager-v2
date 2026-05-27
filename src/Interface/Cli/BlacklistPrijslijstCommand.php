<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstAlreadyBlacklistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricelist:blacklist',
    description: 'Plaats een prijslijst-ID op de blacklist — drift-rapporten skippen die lijst dan volledig.',
)]
final class BlacklistPrijslijstCommand extends Command
{
    public function __construct(private readonly PrijslijstBlacklistRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Prijslijst-ID uit AFAS (bv. 011)')
            ->addArgument('reden', InputArgument::REQUIRED, 'Reden voor blacklist (bv. "IOK — kleine klant-specifieke catalogus")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');
        $reden = (string) $input->getArgument('reden');

        try {
            $this->repository->add($id, $reden);
        } catch (PrijslijstAlreadyBlacklistedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf("Prijslijst '%s' op de blacklist gezet — reden: %s", $id, $reden));

        return Command::SUCCESS;
    }
}
