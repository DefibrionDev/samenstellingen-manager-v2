<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;

/**
 * Synchroniseer publicatie-state (per base × website) naar AFAS. Voor elke
 * base met SKU bouwen we een flag-map (per website: Sync + Tonen op true/false)
 * en PUT'en die op de base zelf én op alle accessoire-varianten in AFAS
 * die met `<baseSku>` of `<baseSku>-` beginnen. Zie PLAN.md §25.
 */
final readonly class SyncPublicationsHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private AfasSamenstellingenRepository $afasSamenstellingen,
        private WebsiteRepository $websites,
        private BasePublicationRepository $publications,
        private PublicationSyncWriter $writer,
    ) {
    }

    public function __invoke(SyncPublications $command): SyncPublicationsResult
    {
        $allWebsites = $this->websites->findAll();
        if ($allWebsites === []) {
            return new SyncPublicationsResult([], 0, []);
        }

        $afasItemcodes = [];
        foreach ($this->afasSamenstellingen->findAll() as $samenstelling) {
            $afasItemcodes[$samenstelling->itemcode] = true;
        }

        $plans = [];
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->id === null || $base->afasItemcode === null) {
                    continue;
                }
                $publishedWebsiteIds = [];
                foreach ($this->publications->findAllForBase($base->id) as $pub) {
                    if ($pub->published) {
                        $publishedWebsiteIds[$pub->websiteId] = true;
                    }
                }
                $flags = [];
                foreach ($allWebsites as $website) {
                    $isPublished = $website->id !== null && isset($publishedWebsiteIds[$website->id]);
                    $flags[$website->ffSyncUuid] = $isPublished;
                    $flags[$website->ffTonenUuid] = $isPublished;
                }

                // Verzamel base + accessoire-varianten (itemcode `<baseSku>` of `<baseSku>-…`).
                $targetItemcodes = $this->collectVariantItemcodes($base->afasItemcode, $afasItemcodes);
                foreach ($targetItemcodes as $itemcode) {
                    $plans[] = new PublicationSyncPlan($itemcode, $base->afasItemcode, $flags);
                    if ($command->limit !== null && count($plans) >= $command->limit) {
                        break 3;
                    }
                }
            }
        }

        $applied = 0;
        $failures = [];
        if ($command->apply) {
            foreach ($plans as $plan) {
                try {
                    $this->writer->apply($plan);
                    ++$applied;
                } catch (PublicationSyncFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new SyncPublicationsResult($plans, $applied, $failures);
    }

    /**
     * @param array<string, bool> $existing
     * @return list<string>
     */
    private function collectVariantItemcodes(string $baseSku, array $existing): array
    {
        $prefix = $baseSku . '-';
        $result = [];
        foreach ($existing as $itemcode => $_) {
            $code = (string) $itemcode; // PHP cast numerieke string-keys naar int — terug naar string.
            if ($code === $baseSku || str_starts_with($code, $prefix)) {
                $result[] = $code;
            }
        }
        sort($result);

        return $result;
    }
}
