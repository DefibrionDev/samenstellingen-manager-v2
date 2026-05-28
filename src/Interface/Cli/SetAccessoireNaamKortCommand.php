<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'accessoire:set-naam-kort',
    description: 'Zet de canonical korte naam (per taal) van een accessoire — gebruikt in variant-naam-templates.',
)]
final class SetAccessoireNaamKortCommand extends Command
{
    public function __construct(private readonly AccessoireRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode (bv. 60110)')
            ->addArgument('taal', InputArgument::REQUIRED, 'Taal-bucket: nl, fr of en')
            ->addArgument('naam', InputArgument::REQUIRED, "Korte canonical naam (bv. 'Rugtas' of 'Sac à dos')");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('itemcode');
        $taal = strtolower(trim((string) $input->getArgument('taal')));
        $naam = (string) $input->getArgument('naam');

        if (!in_array($taal, ['nl', 'fr', 'en'], true)) {
            $io->error("Onbekende taal '$taal' — gebruik nl, fr of en.");

            return Command::INVALID;
        }

        try {
            $this->repository->updateNaamKort($itemcode, $taal, $naam);
        } catch (AccessoireNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Accessoire '%s' naam_kort_%s bijgewerkt naar '%s'.", $itemcode, $taal, trim($naam)));

        return Command::SUCCESS;
    }
}
