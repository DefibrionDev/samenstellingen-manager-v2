<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Domain\Website\WebsiteAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'website:add',
    description: 'Voeg een AFAS-website-bestemming toe (bv. Reseller NL/FR/DE) met de vrije-veld-UUIDs voor Sync en Tonen.',
)]
final class AddWebsiteCommand extends Command
{
    public function __construct(private readonly WebsiteRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('naam', InputArgument::REQUIRED, 'Website-naam (bv. "Reseller NL")')
            ->addArgument('ff-sync-uuid', InputArgument::REQUIRED, 'AFAS free-field UUID voor Sync_*')
            ->addArgument('ff-tonen-uuid', InputArgument::REQUIRED, 'AFAS free-field UUID voor Tonen_*');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $naam = (string) $input->getArgument('naam');
        $sync = (string) $input->getArgument('ff-sync-uuid');
        $tonen = (string) $input->getArgument('ff-tonen-uuid');

        try {
            $saved = $this->repository->save(new Website(null, $naam, $sync, $tonen));
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (WebsiteAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "Website '%s' toegevoegd (id=%d, sync=%s, tonen=%s).",
            $saved->name,
            $saved->id ?? 0,
            $saved->ffSyncUuid,
            $saved->ffTonenUuid,
        ));

        return Command::SUCCESS;
    }
}
