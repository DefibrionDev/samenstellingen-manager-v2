<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final readonly class ListMissingVariantsHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupVariantRepository $variantRepository,
        private GroupBaseItemRepository $baseItemRepository,
    ) {
    }

    /**
     * @return list<MissingVariantRow>
     */
    public function __invoke(ListMissingVariants $command): array
    {
        $rows = [];
        foreach ($this->groupRepository->findAll() as $group) {
            $variants = $this->variantRepository->findAllForGroup($group->familyHeadItemcode);

            $baseAfasSkuByBaseId = [];
            foreach ($variants as $variant) {
                if (
                    $variant->accessoireItemcode === null
                    && $variant->afasStatus === 'matched'
                    && $variant->afasSamenstellingItemcode !== null
                ) {
                    $baseAfasSkuByBaseId[$variant->baseId] = $variant->afasSamenstellingItemcode;
                }
            }

            foreach ($variants as $variant) {
                if ($variant->afasStatus !== 'no_match') {
                    continue;
                }
                $rows[] = $this->buildRow($group->name, $variant, $baseAfasSkuByBaseId);
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $baseAfasSkuByBaseId
     */
    private function buildRow(string $groupName, GroupVariant $variant, array $baseAfasSkuByBaseId): MissingVariantRow
    {
        $baseAfasSku = $baseAfasSkuByBaseId[$variant->baseId] ?? '';

        $baseItems = $this->baseItemRepository->findAllForBase($variant->baseId);
        $bom = [];
        foreach ($baseItems as $item) {
            $bom[] = $item->itemcode;
        }
        if ($variant->accessoireItemcode !== null) {
            $bom[] = $variant->accessoireItemcode;
        }

        $accessoireItemcode = $variant->accessoireItemcode ?? '';
        $suggestedSku = '';
        if ($baseAfasSku !== '' && $accessoireItemcode !== '') {
            $suggestedSku = $baseAfasSku . '-' . $accessoireItemcode;
        } elseif ($baseAfasSku !== '') {
            $suggestedSku = $baseAfasSku;
        }

        return new MissingVariantRow(
            $groupName,
            $variant->baseName,
            $baseAfasSku,
            $accessoireItemcode,
            $variant->accessoireLabel ?? '',
            $bom,
            $suggestedSku,
        );
    }
}
