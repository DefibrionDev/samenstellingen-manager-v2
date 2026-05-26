<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoire;
use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoireHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
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
            ->addArgument('label', InputArgument::REQUIRED, 'Beschrijvend label (bv. "ARKY witte binnenkast")')
            ->addArgument('delta-eur', InputArgument::REQUIRED, "Prijs-toeslag t.o.v. base in euro's (bv. 79 of 79,50). Latere prijs-audit gebruikt dit als ground truth.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $deltaCents = EuroParser::toCents((string) $input->getArgument('delta-eur'));
            $accessoire = ($this->handler)(new CreateAccessoire(
                (string) $input->getArgument('itemcode'),
                (string) $input->getArgument('label'),
                $deltaCents,
            ));
        } catch (AccessoireAlreadyExistsException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf(
            "Accessoire '%s' (%s) toegevoegd — toeslag %s.",
            $accessoire->itemcode,
            $accessoire->label,
            EuroParser::formatCents($accessoire->deltaCents),
        ));

        return Command::SUCCESS;
    }
}
