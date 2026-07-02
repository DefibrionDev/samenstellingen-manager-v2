<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * INTERNE subset-berekening: alleen de no_match-varianten die `variants:fix-missing`
 * daadwerkelijk kan aanmaken (base gematcht + voorgestelde itemcode bestaat nog niet).
 * Voor de gebruikersgerichte audit — inclusief de rijen die hier weggefilterd worden
 * en het waarom — zie {@see ListNoMatchVariantsHandler} (`audit:no-match`, kolom actie).
 */
final readonly class ListMissingVariantsHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupVariantRepository $variantRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * Retourneert alleen variants die `variants:fix-missing --apply` daadwerkelijk
     * zou aanmaken: status `no_match` én verwachte AFAS-SKU bestaat (nog) niet
     * in `afas_samenstellingen`. Variants met een lege voorgestelde SKU (geen
     * base-AFAS-koppeling, geen accessoire-code) vallen ook af — daar weten we
     * niet welk itemcode we zouden POSTen.
     *
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
                $row = $this->buildRow($group->name, $group->familyHeadItemcode, $variant, $baseAfasSkuByBaseId);
                if ($row->verwachteSkuVoorstel === '') {
                    continue;
                }
                if ($this->afasSamenstellingen->findByItemcode($row->verwachteSkuVoorstel) !== null) {
                    continue;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $baseAfasSkuByBaseId
     */
    private function buildRow(string $groupName, string $familyHead, GroupVariant $variant, array $baseAfasSkuByBaseId): MissingVariantRow
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
            $familyHead,
            $variant->baseName,
            $baseAfasSku,
            $accessoireItemcode,
            $variant->accessoireLabel ?? '',
            $bom,
            $suggestedSku,
        );
    }
}
