<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'website:remove',
    description: 'Verwijder een website. Cascade ruimt alle base_publications voor deze website op (gepubliceerde varianten verliezen dus hun publicatie-state).',
)]
final class RemoveWebsiteCommand extends Command
{
    public function __construct(private readonly WebsiteRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('naam', InputArgument::REQUIRED, 'Naam van de website om te verwijderen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $naam = (string) $input->getArgument('naam');

        $website = $this->repository->findByName($naam);
        if ($website === null) {
            $io->warning(sprintf("Website '%s' bestaat niet — niets te doen.", $naam));

            return Command::SUCCESS;
        }

        $this->repository->delete($naam);
        $io->success(sprintf("Website '%s' verwijderd.", $naam));

        return Command::SUCCESS;
    }
}
