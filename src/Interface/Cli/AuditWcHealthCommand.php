<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Woo\AuditWcHealth;
use Defibrion\Samenstellingen\Application\Woo\WcHealthAuditHandler;
use Defibrion\Samenstellingen\Application\Woo\WcHealthCell;
use Defibrion\Samenstellingen\Application\Woo\WcHealthRow;
use Defibrion\Samenstellingen\Application\Woo\WcHealthStatus;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'audit:wc-health',
    description: 'Detecteer managed AFAS-itemcodes met fout WC-type, status of presence per shop.',
)]
final class AuditWcHealthCommand extends Command
{
    public function __construct(
        private readonly WcHealthAuditHandler $handler,
        private readonly WooCommerceStoreRepository $stores,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Beperk tot één shop-naam.')
            ->addOption('missing', null, InputOption::VALUE_NONE, 'Toon alleen rijen met ≥1 missing-cel.')
            ->addOption('wrong-type', null, InputOption::VALUE_NONE, 'Toon alleen rijen met ≥1 wrong-type-cel.')
            ->addOption('not-publish', null, InputOption::VALUE_NONE, 'Toon alleen rijen met ≥1 not-publish-cel.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $storeOption = $input->getOption('store');
        $storeName = is_string($storeOption) && $storeOption !== '' ? $storeOption : null;

        try {
            $rows = ($this->handler)(new AuditWcHealth($storeName));
        } catch (WooCommerceStoreNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $filterMissing = (bool) $input->getOption('missing');
        $filterWrong = (bool) $input->getOption('wrong-type');
        $filterNotPublish = (bool) $input->getOption('not-publish');
        $anyFilter = $filterMissing || $filterWrong || $filterNotPublish;

        if ($anyFilter) {
            $rows = array_values(array_filter($rows, static function (WcHealthRow $row) use ($filterMissing, $filterWrong, $filterNotPublish): bool {
                foreach ($row->cellsByStore as $cell) {
                    if ($filterMissing && $cell->healthStatus === WcHealthStatus::Missing) {
                        return true;
                    }
                    if ($filterWrong && $cell->healthStatus === WcHealthStatus::WrongType) {
                        return true;
                    }
                    if ($filterNotPublish && $cell->healthStatus === WcHealthStatus::NotPublish) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($rows === []) {
            $io->success('Geen managed itemcodes met issues in de geselecteerde shop(s).');

            return Command::SUCCESS;
        }

        $storesForHeader = [];
        if ($storeName === null) {
            $storesForHeader = $this->stores->findAll();
        } else {
            $single = $this->stores->findByName($storeName);
            if ($single !== null) {
                $storesForHeader = [$single];
            }
        }
        $header = ['afas-itemcode', 'verwacht'];
        $storeIds = [];
        foreach ($storesForHeader as $store) {
            if ($store->id === null) {
                continue;
            }
            $header[] = $store->name;
            $storeIds[] = $store->id;
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $line = [$row->afasItemcode, $row->expectedType];
            foreach ($storeIds as $sid) {
                $line[] = $this->renderCell($row->cellsByStore[$sid] ?? null);
            }
            $tableRows[] = $line;
        }
        $io->table($header, $tableRows);
        $io->note(sprintf('%d itemcode(s) met issues.', count($rows)));

        return Command::SUCCESS;
    }

    private function renderCell(?WcHealthCell $cell): string
    {
        if ($cell === null) {
            return '—';
        }

        return match ($cell->healthStatus) {
            WcHealthStatus::Ok => '✓ ' . ($cell->wcProductId ?? '?'),
            WcHealthStatus::WrongType => '⚠ ' . ($cell->actualType ?? '?') . ':' . ($cell->wcProductId ?? '?'),
            WcHealthStatus::NotPublish => '◐ ' . ($cell->wcProductId ?? '?') . ' (' . ($cell->status ?? '?') . ')',
            WcHealthStatus::Missing => '✗',
        };
    }
}
