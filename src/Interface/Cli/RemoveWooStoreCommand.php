<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Woo\RemoveWooStore;
use Defibrion\Samenstellingen\Application\Woo\RemoveWooStoreHandler;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wc:store:remove',
    description: 'Verwijder een WooCommerce-shop. Cascade ruimt alle bijhorende product-snapshot-rijen op.',
)]
final class RemoveWooStoreCommand extends Command
{
    public function __construct(private readonly RemoveWooStoreHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Shop-naam zoals geregistreerd via `wc:store:add`')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Sla bevestigings-prompt over');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $force = (bool) $input->getOption('force');

        if (!$force && !$io->confirm(sprintf("Store '%s' + alle product-snapshot-rijen verwijderen?", $name), false)) {
            $io->note('Geannuleerd.');

            return Command::SUCCESS;
        }

        try {
            $deletedId = ($this->handler)(new RemoveWooStore($name));
        } catch (WooCommerceStoreNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Store '%s' (id=%d) verwijderd.", $name, $deletedId));

        return Command::SUCCESS;
    }
}
