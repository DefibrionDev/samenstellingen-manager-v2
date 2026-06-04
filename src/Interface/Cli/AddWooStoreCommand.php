<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Woo\AddWooStore;
use Defibrion\Samenstellingen\Application\Woo\AddWooStoreHandler;
use Defibrion\Samenstellingen\Application\Woo\InvalidWooStoreException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wc:store:add',
    description: 'Registreer een WooCommerce-shop met REST-credentials voor latere `wc:pull`-aanroepen.',
)]
final class AddWooStoreCommand extends Command
{
    public function __construct(private readonly AddWooStoreHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Unieke shop-naam (bv. "defibrion.nl")')
            ->addArgument('base-url', InputArgument::REQUIRED, 'Shop base-URL (moet beginnen met https://)')
            ->addArgument('consumer-key', InputArgument::REQUIRED, 'WooCommerce REST consumer key (ck_...)')
            ->addArgument('consumer-secret', InputArgument::REQUIRED, 'WooCommerce REST consumer secret (cs_...)')
            ->addOption('meta-key', null, InputOption::VALUE_REQUIRED, 'Meta-key waarin de AFAS-itemcode op WC-producten staat', '_afas_itemcode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $store = ($this->handler)(new AddWooStore(
                (string) $input->getArgument('name'),
                (string) $input->getArgument('base-url'),
                (string) $input->getArgument('consumer-key'),
                (string) $input->getArgument('consumer-secret'),
                (string) $input->getOption('meta-key'),
            ));
        } catch (InvalidWooStoreException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "Store '%s' toegevoegd (id=%d, base-url=%s, meta-key=%s).",
            $store->name,
            $store->id ?? 0,
            $store->baseUrl,
            $store->afasItemcodeMetaKey,
        ));

        return Command::SUCCESS;
    }
}
