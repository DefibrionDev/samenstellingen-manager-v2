<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Handmatige fallback voor ambigue portal-CSV-rijen: koppel een specifieke
 * AFAS-samenstelling (via z'n itemcode) als base aan een groep. Naam en BOM
 * komen uit de lokale AFAS-snapshot — geen interpretatie, geen filter.
 *
 * Gebruik bij data-drift in AFAS (zoals `11650-60110` zonder accessoire in z'n
 * BOM) die de filter niet kan onderscheiden van een legitieme base.
 */
final readonly class AddBaseFromAfasHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupBaseItemRepository $items,
        private GroupVariantRepository $variants,
        private AfasSamenstellingenRepository $afas,
    ) {
    }

    public function __invoke(AddBaseFromAfas $command): GroupBase
    {
        $group = $this->groups->findByFamilyHeadItemcode($command->familyHeadItemcode);
        if ($group === null) {
            throw GroupNotFoundException::forFamilyHeadItemcode($command->familyHeadItemcode);
        }

        $samenstelling = $this->afas->findByItemcode($command->afasItemcode);
        if ($samenstelling === null) {
            throw AfasSamenstellingNotInSnapshotException::forItemcode($command->afasItemcode);
        }

        try {
            $persisted = $this->bases->saveForGroup(
                $group->familyHeadItemcode,
                new GroupBase(null, $samenstelling->name, $command->languageCode, $samenstelling->itemcode),
            );
        } catch (BaseAlreadyExistsException $e) {
            // Re-throw zodat de CLI 'm netjes kan tonen — geen stille skip.
            throw $e;
        }

        if ($persisted->id !== null) {
            foreach ($samenstelling->bomItemcodes as $itemcode) {
                try {
                    $this->items->saveForBase(
                        $persisted->id,
                        new GroupBaseItem($itemcode, $itemcode),
                    );
                } catch (BaseItemAlreadyExistsException) {
                    // Idempotent op item-niveau.
                }
            }
        }

        $this->variants->regenerateForGroup($group->familyHeadItemcode);

        return $persisted;
    }
}
