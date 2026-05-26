<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseFromAfas;
use Defibrion\Samenstellingen\Application\Group\AddBaseFromAfasHandler;
use Defibrion\Samenstellingen\Application\Group\AfasSamenstellingNotInSnapshotException;
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
    name: 'group:add-base-from-afas',
    description: 'Voeg handmatig een base toe aan een groep door een AFAS-samenstellingens-SKU expliciet te kiezen. Naam + BOM komen uit de lokale snapshot. Workaround voor ambigue portal-CSV-import-gevallen.',
)]
final class AddBaseFromAfasCommand extends Command
{
    public function __construct(private readonly AddBaseFromAfasHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('family-head', InputArgument::REQUIRED, 'Family-head itemcode van de groep (bv. 11683)')
            ->addArgument('afas-itemcode', InputArgument::REQUIRED, 'AFAS-samenstellings-itemcode die als base moet (bv. 11650)')
            ->addArgument('language-code', InputArgument::REQUIRED, 'Taal-code (NL, FR, DE, DA, EN, UK, …)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $base = ($this->handler)(new AddBaseFromAfas(
                (string) $input->getArgument('family-head'),
                (string) $input->getArgument('afas-itemcode'),
                (string) $input->getArgument('language-code'),
            ));
        } catch (GroupNotFoundException|AfasSamenstellingNotInSnapshotException|BaseAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Base #%d '%s' (%s) toegevoegd aan groep %s — AFAS-SKU %s. Variant-matrix opnieuw gegenereerd.",
            $base->id ?? 0,
            $base->name,
            $base->languageCode,
            (string) $input->getArgument('family-head'),
            $base->afasItemcode ?? '',
        ));

        return Command::SUCCESS;
    }
}
