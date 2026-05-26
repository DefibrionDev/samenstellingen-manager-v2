<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\RemoveBase;
use Defibrion\Samenstellingen\Application\Group\RemoveBaseHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'group:remove-base',
    description: 'Verwijder een base uit z\'n groep. FK-cascade ruimt base-items en varianten op; variant-matrix wordt opnieuw gegenereerd.',
)]
final class RemoveBaseCommand extends Command
{
    public function __construct(private readonly RemoveBaseHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('base-id', InputArgument::REQUIRED, 'Numeriek base-id (zie group:show of /api/groups/{familyHead})');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rawId = (string) $input->getArgument('base-id');
        if (!ctype_digit($rawId)) {
            $io->error("base-id moet numeriek zijn, kreeg '$rawId'.");

            return Command::INVALID;
        }

        try {
            $result = ($this->handler)(new RemoveBase((int) $rawId));
        } catch (BaseNotFoundException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            "Base #%d '%s' verwijderd%s. Variant-matrix opnieuw gegenereerd.",
            $result->baseId,
            $result->baseName,
            $result->familyHeadItemcode !== null ? " uit groep {$result->familyHeadItemcode}" : '',
        ));

        return Command::SUCCESS;
    }
}
