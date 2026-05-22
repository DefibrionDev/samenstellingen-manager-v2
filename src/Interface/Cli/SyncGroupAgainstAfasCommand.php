<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfas;
use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfasHandler;
use Defibrion\Samenstellingen\Domain\Afas\AmbiguousMatchException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:sync-afas',
    description: 'Match lokale varianten tegen de lokale AFAS-snapshot (draai eerst `afas:pull` om die te verversen).',
)]
final class SyncGroupAgainstAfasCommand extends Command
{
    public function __construct(private readonly SyncGroupAgainstAfasHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'family-head-itemcode',
            InputArgument::REQUIRED,
            'AFAS family-head itemcode (bv. 52112)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemcode = (string) $input->getArgument('family-head-itemcode');

        try {
            $summary = ($this->handler)(new SyncGroupAgainstAfas($itemcode));
        } catch (GroupNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (AmbiguousMatchException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'Lokale snapshot bevat %d AFAS-samenstellingen.',
            $summary->afasSamenstellingenCount,
        ));
        $io->newLine();

        foreach ($summary->matched as $entry) {
            $variant = $entry['variant'];
            $io->writeln(sprintf(
                '  <info>✓</info> Variant #%d (base #%d%s) → AFAS %s',
                $variant->id ?? 0,
                $variant->baseId,
                $variant->accessoireItemcode !== null
                    ? ' + ' . $variant->accessoireItemcode
                    : '',
                $entry['afasItemcode'],
            ));
        }
        foreach ($summary->notMatched as $variant) {
            $io->writeln(sprintf(
                '  <comment>✗</comment> Variant #%d (base #%d%s) → geen match in AFAS',
                $variant->id ?? 0,
                $variant->baseId,
                $variant->accessoireItemcode !== null
                    ? ' + ' . $variant->accessoireItemcode
                    : '',
            ));
        }

        $io->newLine();
        $io->success(sprintf(
            '%d gematcht, %d ontbreekt in AFAS.',
            $summary->matchCount(),
            $summary->noMatchCount(),
        ));

        return $summary->noMatchCount() === 0 ? Command::SUCCESS : Command::SUCCESS;
    }
}
