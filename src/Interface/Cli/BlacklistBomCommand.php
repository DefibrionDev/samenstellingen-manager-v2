<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Afas\BlacklistBomCode;
use Defibrion\Samenstellingen\Application\Afas\BlacklistBomCodeHandler;
use Defibrion\Samenstellingen\Domain\Afas\BomCodeAlreadyBlacklistedException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'samenstelling:blacklist-bom',
    description: 'Blacklist een BOM-itemcode: AFAS-samenstellingen die deze code in hun BOM hebben tellen niet meer als base-kandidaat tijdens portal-CSV-import.',
)]
final class BlacklistBomCommand extends Command
{
    public function __construct(private readonly BlacklistBomCodeHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS BOM-itemcode (bv. 81311 voor de Waalse stickerset)')
            ->addArgument('reason', InputArgument::REQUIRED, 'Reden voor de blacklist (bv. "Waalse stickerset — niet de basis-taal voor de portal")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $entry = ($this->handler)(new BlacklistBomCode(
                (string) $input->getArgument('itemcode'),
                (string) $input->getArgument('reason'),
            ));
        } catch (BomCodeAlreadyBlacklistedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "BOM-itemcode '%s' op de blacklist gezet — reden: %s",
            $entry->itemcode,
            $entry->reason,
        ));

        return Command::SUCCESS;
    }
}
