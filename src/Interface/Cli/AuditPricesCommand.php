<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\AuditPrices;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:prices',
    description: 'Detecteer prijs-drift: variant-toeslag wijkt af van accessoires.delta_eur of variant ontbreekt in een prijslijst waar de base wél in staat.',
)]
final class AuditPricesCommand extends Command
{
    public function __construct(private readonly PriceAuditHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Beperk output tot N rijen', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = ($this->handler)(new AuditPrices());

        if ($rows === []) {
            $io->success('Geen prijs-drift gevonden — alle varianten kloppen met hun base + toeslag.');

            return Command::SUCCESS;
        }

        $drift = 0;
        $missing = 0;
        foreach ($rows as $r) {
            if ($r->status === 'missing') {
                ++$missing;
            } else {
                ++$drift;
            }
        }

        $limit = (int) $input->getOption('limit');
        $shown = $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
        $table = [];
        foreach ($shown as $r) {
            $lijstLabel = $r->prijslijstOmschrijving !== null
                ? sprintf('%s — %s', $r->prijslijstId, $r->prijslijstOmschrijving)
                : $r->prijslijstId;
            $table[] = [
                $r->variantAfasItemcode,
                $r->accessoireItemcode,
                $lijstLabel,
                $r->status,
                EuroParser::formatCents($r->basePrijsCents),
                $r->variantPrijsCents !== null ? EuroParser::formatCents($r->variantPrijsCents) : '—',
                EuroParser::formatCents($r->expectedDeltaCents),
                $r->actualDeltaCents !== null ? EuroParser::formatCents($r->actualDeltaCents) : '—',
            ];
        }
        $io->section(sprintf('%d rij(en) — %d toeslag-drift, %d missing', count($rows), $drift, $missing));
        $io->table(
            ['Variant', 'Acc.', 'Prijslijst', 'Status', 'Base', 'Variant', 'Verwacht', 'Werkelijk'],
            $table,
        );
        if ($limit > 0 && count($rows) > $limit) {
            $io->writeln(sprintf('<comment>%d rij(en) niet getoond.</comment>', count($rows) - $limit));
        }

        return Command::FAILURE;
    }
}
