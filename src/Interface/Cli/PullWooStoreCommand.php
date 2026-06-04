<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Woo\PullWooStore;
use Defibrion\Samenstellingen\Application\Woo\PullWooStoreHandler;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wc:pull',
    description: 'Ververs de WooCommerce-product-snapshot voor één of alle geregistreerde shops.',
)]
final class PullWooStoreCommand extends Command
{
    public function __construct(private readonly PullWooStoreHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'store',
            null,
            InputOption::VALUE_REQUIRED,
            'Eén shop-naam. Default = alle geregistreerde shops.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = $input->getOption('store');

        try {
            $result = ($this->handler)(new PullWooStore(is_string($store) && $store !== '' ? $store : null));
        } catch (WooCommerceStoreNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($result->itemsByStore === []) {
            $io->note('Geen shops geregistreerd. Voeg toe via `wc:store:add`.');

            return Command::SUCCESS;
        }

        $rows = [];
        $total = 0;
        foreach ($result->itemsByStore as $name => $count) {
            $rows[] = [$name, $count];
            $total += $count;
        }
        $io->table(['store', 'opgehaalde items (parents + variations)'], $rows);
        $io->success(sprintf('Snapshot ververst: %d items totaal.', $total));

        return Command::SUCCESS;
    }
}
