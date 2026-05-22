<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoire;
use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoireHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'accessoire:create',
    description: 'Registreer een accessoire in de catalogus (kan daarna aan meerdere groepen worden gekoppeld).',
)]
final class CreateAccessoireCommand extends Command
{
    public function __construct(private readonly CreateAccessoireHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode (bv. 60112)')
            ->addArgument('label', InputArgument::REQUIRED, 'Beschrijvend label (bv. "ARKY witte binnenkast")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $accessoire = ($this->handler)(new CreateAccessoire(
                (string) $input->getArgument('itemcode'),
                (string) $input->getArgument('label'),
            ));
        } catch (AccessoireAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Accessoire '%s' (%s) toegevoegd aan de catalogus.",
            $accessoire->itemcode,
            $accessoire->label,
        ));

        return Command::SUCCESS;
    }
}
