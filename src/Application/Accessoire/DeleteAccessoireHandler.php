<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Verwijdert een accessoire uit de catalogus. FK-cascade ruimt `group_accessoires`-
 * koppelingen op + alle `group_variants` met die accessoire. We regenereren daarna
 * de variant-matrix voor elke groep die de accessoire gekoppeld had, zodat eventuele
 * stale tellers/derived rijen kloppen.
 */
final readonly class DeleteAccessoireHandler
{
    public function __construct(
        private AccessoireRepository $accessoires,
        private GroupRepository $groups,
        private GroupAccessoireRepository $links,
        private GroupVariantRepository $variants,
    ) {
    }

    public function __invoke(DeleteAccessoire $command): DeleteAccessoireResult
    {
        $affected = [];
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->links->findAllForGroup($group->familyHeadItemcode) as $linked) {
                if ($linked->itemcode === $command->itemcode) {
                    $affected[] = $group->familyHeadItemcode;
                    break;
                }
            }
        }

        // Throwt AccessoireNotFoundException als hij niet bestaat.
        $this->accessoires->delete($command->itemcode);

        foreach ($affected as $familyHead) {
            $this->variants->regenerateForGroup($familyHead);
        }

        return new DeleteAccessoireResult($command->itemcode, $affected);
    }
}
