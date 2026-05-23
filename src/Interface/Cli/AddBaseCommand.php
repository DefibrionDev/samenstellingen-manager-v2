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
    description: 'Voeg een base (taalvariant van de basissamenstelling) toe aan een groep. Items van de base worden apart aangemaakt via group:add-base-item.',
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
            ->addArgument(
                'family-head-itemcode',
                InputArgument::REQUIRED,
                'AFAS family-head itemcode van de groep (bv. 52112)',
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Naam van de base (bv. "AED pakket: Reanibex 100 Semi-Auto NL incl. safeset en stickerset")',
            )
            ->addArgument(
                'language-code',
                InputArgument::REQUIRED,
                'Taal-code (NL, FR, DE, UK, EN, WAL, …)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('family-head-itemcode');
        $name = (string) $input->getArgument('name');
        $language = (string) $input->getArgument('language-code');

        try {
            $base = ($this->handler)(new AddBaseToGroup($itemcode, $name, $language));
        } catch (GroupNotFoundException | BaseAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Base #%d aangemaakt: '%s' (%s).",
            $base->id ?? 0,
            $base->name,
            $base->languageCode,
        ));

        return Command::SUCCESS;
    }
}
