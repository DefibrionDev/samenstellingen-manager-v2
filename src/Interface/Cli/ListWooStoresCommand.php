<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wc:store:list',
    description: 'Toon alle geregistreerde WooCommerce-shops met gemaskerde credentials.',
)]
final class ListWooStoresCommand extends Command
{
    public function __construct(private readonly WooCommerceStoreRepository $repository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stores = $this->repository->findAll();
        if ($stores === []) {
            $io->note('Geen WooCommerce-shops geregistreerd. Voeg toe via `wc:store:add`.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($stores as $store) {
            $rows[] = [
                $store->id,
                $store->name,
                $store->baseUrl,
                $store->afasItemcodeMetaKey,
                $this->mask($store->consumerKey),
            ];
        }
        $io->table(['id', 'name', 'base_url', 'meta_key', 'consumer_key'], $rows);

        return Command::SUCCESS;
    }

    private function mask(string $secret): string
    {
        if (strlen($secret) <= 6) {
            return str_repeat('•', strlen($secret));
        }

        return substr($secret, 0, 6) . '…';
    }
}
