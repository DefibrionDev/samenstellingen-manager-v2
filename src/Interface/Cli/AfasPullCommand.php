<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingen;
use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingenHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'afas:pull',
    description: 'Vervang de lokale AFAS-snapshot met alle samenstellingen (type_id=7) + BOMs uit AFAS.',
)]
final class AfasPullCommand extends Command
{
    public function __construct(private readonly PullAfasSamenstellingenHandler $handler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Bezig met ophalen uit AFAS… (kan even duren bij grote dumps)');

        $result = ($this->handler)(new PullAfasSamenstellingen());

        $io->success(sprintf(
            '%d samenstellingen + %d artikelen + %d prijzen + %d prijslijsten opgeslagen in de lokale snapshot.',
            $result->samenstellingen,
            $result->articles,
            $result->prijzen,
            $result->prijslijsten,
        ));

        if ($result->basesRenamed > 0) {
            $io->writeln(sprintf(
                '<info>%d base(s) hernoemd uit AFAS</info>.',
                $result->basesRenamed,
            ));
        }

        $sync = $result->sync;
        if ($sync->groupsProcessed > 0) {
            $io->writeln(sprintf(
                'Auto-sync: %d groepen verwerkt → <info>%d matched</info>, <comment>%d no_match</comment>.',
                $sync->groupsProcessed,
                $sync->matched,
                $sync->noMatch,
            ));
        } elseif ($sync->groupsSkipped > 0) {
            foreach ($sync->skipReasons as $reason) {
                $io->writeln('<comment>Auto-sync overgeslagen:</comment> ' . $reason);
            }
        }

        return Command::SUCCESS;
    }
}
