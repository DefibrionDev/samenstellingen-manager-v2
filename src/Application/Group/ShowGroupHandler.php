<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final readonly class ShowGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupBaseRepository $baseRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private GroupAccessoireRepository $linkRepository,
        private GroupVariantRepository $variantRepository,
    ) {
    }

    public function __invoke(ShowGroup $query): GroupOverview
    {
        $group = $this->groupRepository->findByFamilyHeadItemcode($query->familyHeadItemcode);
        if ($group === null) {
            throw GroupNotFoundException::forFamilyHeadItemcode($query->familyHeadItemcode);
        }

        $bases = $this->baseRepository->findAllForGroup($query->familyHeadItemcode);
        $accessoires = $this->linkRepository->findAllForGroup($query->familyHeadItemcode);
        $variants = $this->variantRepository->findAllForGroup($query->familyHeadItemcode);

        $variantsWithBom = [];
        foreach ($variants as $variant) {
            $variantsWithBom[] = new GroupVariantWithBom(
                $variant,
                $this->buildBom($variant),
            );
        }

        return new GroupOverview($group, $bases, $accessoires, $variantsWithBom);
    }

    /**
     * @return list<BomItem>
     */
    private function buildBom(GroupVariant $variant): array
    {
        $bom = [];
        foreach ($this->baseItemRepository->findAllForBase($variant->baseId) as $item) {
            $bom[] = new BomItem($item->itemcode, $item->name);
        }
        if ($variant->accessoireItemcode !== null && $variant->accessoireLabel !== null) {
            $bom[] = new BomItem($variant->accessoireItemcode, $variant->accessoireLabel);
        }

        return $bom;
    }
}
