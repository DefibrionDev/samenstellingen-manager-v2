<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Woo\ListWooIndex;
use Defibrion\Samenstellingen\Application\Woo\ListWooIndexHandler;
use Defibrion\Samenstellingen\Application\Woo\WooIndexCell;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wc:index',
    description: 'Toon de WooCommerce-index per AFAS-itemcode × store, of de orphan-/missend-deelverzameling.',
)]
final class ListWooIndexCommand extends Command
{
    public function __construct(
        private readonly ListWooIndexHandler $handler,
        private readonly WooCommerceStoreRepository $stores,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Beperk tot één shop-naam.')
            ->addOption('missing', null, InputOption::VALUE_NONE, 'Toon alleen managed-itemcodes die in minstens één shop ontbreken.')
            ->addOption('orphan', null, InputOption::VALUE_NONE, 'Toon WC-producten zonder match in onze AFAS-managed-set.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $storeOption = $input->getOption('store');
        $storeName = is_string($storeOption) && $storeOption !== '' ? $storeOption : null;
        $missingOnly = (bool) $input->getOption('missing');
        $orphanOnly = (bool) $input->getOption('orphan');

        try {
            $result = ($this->handler)(new ListWooIndex($storeName, $missingOnly, $orphanOnly));
        } catch (WooCommerceStoreNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($orphanOnly) {
            if ($result->orphans === []) {
                $io->success('Geen orphans — alle WC-producten matchen op de AFAS-managed-set.');

                return Command::SUCCESS;
            }
            $rows = [];
            foreach ($result->orphans as $orphan) {
                $rows[] = [
                    $orphan->storeName,
                    $orphan->wcProductId,
                    $orphan->wcType,
                    $orphan->sku ?? '—',
                    $this->truncate($orphan->name, 50),
                    $orphan->status,
                    $orphan->afasItemcode ?? '— (geen meta)',
                ];
            }
            $io->table(['store', 'wc-id', 'type', 'sku', 'naam', 'status', 'afas-meta'], $rows);
            $io->note(sprintf('%d orphan(s) gevonden.', count($result->orphans)));

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
        if ($result->rows === []) {
            $io->success('Geen rijen — alle managed-itemcodes zijn op alle geselecteerde shops aanwezig (of er zijn er geen).');

            return Command::SUCCESS;
        }

        $header = ['AFAS-itemcode'];
        $storeIds = [];
        foreach ($storesForHeader as $store) {
            if ($store->id === null) {
                continue;
            }
            $header[] = $store->name;
            $storeIds[] = $store->id;
        }
        $tableRows = [];
        foreach ($result->rows as $row) {
            $line = [$row->afasItemcode];
            foreach ($storeIds as $sid) {
                $line[] = $this->renderCell($row->cellsByStore[$sid] ?? null);
            }
            $tableRows[] = $line;
        }
        $io->table($header, $tableRows);
        $io->note(sprintf(
            '%d rij(en) — %s. Voor de WC-producten zonder AFAS-match: run met `--orphan`.',
            count($result->rows),
            $missingOnly ? 'alleen rows met ≥1 ontbrekende store-cel' : 'volledige index',
        ));

        return Command::SUCCESS;
    }

    private function renderCell(?WooIndexCell $cell): string
    {
        if ($cell === null) {
            return '✗';
        }

        return match ($cell->status) {
            'publish' => '✓ ' . $cell->wcProductId,
            'draft' => '◐ ' . $cell->wcProductId . ' (draft)',
            'private' => '◐ ' . $cell->wcProductId . ' (private)',
            'pending' => '◐ ' . $cell->wcProductId . ' (pending)',
            'trash' => '✗ ' . $cell->wcProductId . ' (trash)',
            default => $cell->status . ' ' . $cell->wcProductId,
        };
    }

    private function truncate(string $value, int $maxLen): string
    {
        if (mb_strlen($value) <= $maxLen) {
            return $value;
        }

        return mb_substr($value, 0, $maxLen - 1) . '…';
    }
}
