<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Accessoire\DeleteAccessoire;
use Defibrion\Samenstellingen\Application\Accessoire\DeleteAccessoireHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'accessoire:delete',
    description: 'Verwijder een accessoire uit de catalogus. Cascade ruimt groepskoppelingen en bijhorende varianten op.',
)]
final class DeleteAccessoireCommand extends Command
{
    public function __construct(private readonly DeleteAccessoireHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('itemcode', InputArgument::REQUIRED, 'AFAS itemcode van de te verwijderen accessoire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = ($this->handler)(new DeleteAccessoire((string) $input->getArgument('itemcode')));
        } catch (AccessoireNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "Accessoire '%s' verwijderd. Variant-matrix opnieuw gegenereerd voor %d groep(en)%s.",
            $result->itemcode,
            count($result->affectedFamilyHeads),
            $result->affectedFamilyHeads === [] ? '' : ': ' . implode(', ', $result->affectedFamilyHeads),
        ));

        return Command::SUCCESS;
    }
}
