<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:rename',
    description: 'Hernoem een groep (weergavenaam). De family-head itemcode blijft ongewijzigd.',
)]
final class RenameGroupCommand extends Command
{
    public function __construct(private readonly GroupRepository $groups)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('family-head', InputArgument::REQUIRED, 'Family-head itemcode van de groep (bv. 52120)')
            ->addArgument('naam', InputArgument::REQUIRED, 'Nieuwe groepsnaam');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $familyHead = (string) $input->getArgument('family-head');
        $naam = trim((string) $input->getArgument('naam'));

        if ($naam === '') {
            $io->error('Groepsnaam mag niet leeg zijn.');

            return Command::FAILURE;
        }

        $huidig = $this->groups->findByFamilyHeadItemcode($familyHead);
        if ($huidig === null) {
            $io->error(sprintf("Groep met family-head '%s' bestaat niet.", $familyHead));

            return Command::FAILURE;
        }

        try {
            $this->groups->rename($familyHead, $naam);
        } catch (GroupNotFoundException|GroupAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Groep '%s' hernoemd: '%s' → '%s'.", $familyHead, $huidig->name, $naam));

        return Command::SUCCESS;
    }
}
