<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gedeelde flow voor `base:publish` en `base:unpublish` — verschilt alleen
 * in de boolean. Houdt de twee CLI's klein en symmetrisch.
 */
final class PublishBaseSupport
{
    public static function run(
        InputInterface $input,
        OutputInterface $output,
        GroupBaseRepository $bases,
        WebsiteRepository $websites,
        BasePublicationRepository $publications,
        bool $published,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $afasItemcode = trim((string) $input->getArgument('afas-itemcode'));
        $websiteNaam = trim((string) $input->getArgument('website-naam'));

        $website = $websites->findByName($websiteNaam);
        if ($website === null || $website->id === null) {
            $io->error(sprintf("Website '%s' niet gevonden. Zie `website:list`.", $websiteNaam));

            return Command::FAILURE;
        }

        $matchingBases = $bases->findAllByAfasItemcode($afasItemcode);
        if ($matchingBases === []) {
            $io->error(sprintf("Geen base gevonden met afas_itemcode '%s'.", $afasItemcode));

            return Command::FAILURE;
        }

        $updated = 0;
        foreach ($matchingBases as $base) {
            if ($base->id === null) {
                continue;
            }
            $publications->setPublished($base->id, $website->id, $published);
            ++$updated;
        }

        $action = $published ? 'gepubliceerd' : 'unpublished';
        $io->success(sprintf(
            "%d base(s) met afas_itemcode '%s' %s op website '%s'.",
            $updated,
            $afasItemcode,
            $action,
            $website->name,
        ));

        return Command::SUCCESS;
    }
}
