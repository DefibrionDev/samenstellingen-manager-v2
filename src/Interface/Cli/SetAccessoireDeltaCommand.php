<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Accessoire\SetAccessoireDelta;
use Defibrion\Samenstellingen\Application\Accessoire\SetAccessoireDeltaHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'accessoire:set-delta',
    description: 'Wijzig de prijs-toeslag (delta) van een bestaande accessoire. Gebruikt door de price-audit als ground truth.',
)]
final class SetAccessoireDeltaCommand extends Command
{
    public function __construct(private readonly SetAccessoireDeltaHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode van een bestaande accessoire (bv. 60112)')
            ->addArgument('delta-eur', InputArgument::REQUIRED, "Nieuwe toeslag in euro's (bv. 79 of 79,50)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('itemcode');

        try {
            $deltaCents = EuroParser::toCents((string) $input->getArgument('delta-eur'));
            ($this->handler)(new SetAccessoireDelta($itemcode, $deltaCents));
        } catch (AccessoireNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf("Accessoire '%s' toeslag bijgewerkt naar %s.", $itemcode, EuroParser::formatCents($deltaCents)));

        return Command::SUCCESS;
    }
}
