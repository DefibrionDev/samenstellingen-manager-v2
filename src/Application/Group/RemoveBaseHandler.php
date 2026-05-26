<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Verwijdert een base uit z'n groep. FK-cascade ruimt group_base_items en
 * group_variants op. Daarna regenereren we de variant-matrix voor de groep
 * zodat eventuele afgeleide tellers kloppen.
 */
final readonly class RemoveBaseHandler
{
    public function __construct(
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
    ) {
    }

    public function __invoke(RemoveBase $command): RemoveBaseResult
    {
        $base = $this->bases->findById($command->baseId);
        if ($base === null) {
            throw BaseNotFoundException::forId($command->baseId);
        }

        $familyHead = $this->bases->findFamilyHeadForBase($command->baseId);

        $this->bases->delete($command->baseId);

        if ($familyHead !== null) {
            $this->variants->regenerateForGroup($familyHead);
        }

        return new RemoveBaseResult($command->baseId, $base->name, $familyHead);
    }
}
