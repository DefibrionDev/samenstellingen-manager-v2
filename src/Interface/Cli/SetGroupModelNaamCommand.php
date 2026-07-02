<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:set-model-naam',
    description: 'Zet de canonical model-naam (per taal) van een groep — gebruikt in variant-naam-templates.',
)]
final class SetGroupModelNaamCommand extends Command
{
    public function __construct(private readonly GroupRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('family-head', InputArgument::REQUIRED, 'Family-head itemcode van de groep (bv. 11111)')
            ->addArgument('taal', InputArgument::REQUIRED, 'Taal-bucket: nl, fr, en of de')
            ->addArgument('naam', InputArgument::REQUIRED, "Canonical model-naam (bv. 'Heartsine Samaritan PAD 350P')");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $familyHead = (string) $input->getArgument('family-head');
        $taal = strtolower(trim((string) $input->getArgument('taal')));
        $naam = (string) $input->getArgument('naam');

        if (!in_array($taal, ['nl', 'fr', 'en', 'de'], true)) {
            $io->error("Onbekende taal '$taal' — gebruik nl, fr, en of de.");

            return Command::INVALID;
        }

        try {
            $this->repository->updateModelNaam($familyHead, $taal, $naam);
        } catch (GroupNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Groep '%s' model_name_%s bijgewerkt naar '%s'.", $familyHead, $taal, trim($naam)));

        return Command::SUCCESS;
    }
}
