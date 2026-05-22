<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Afas\VariantMatcher;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use RuntimeException;

final readonly class SyncGroupAgainstAfasHandler
{
    public function __construct(
        private GroupVariantRepository $variantRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private AfasSamenstellingenRepository $afasRepository,
        private VariantMatcher $matcher,
    ) {
    }

    public function __invoke(SyncGroupAgainstAfas $command): SyncSummary
    {
        $variants = $this->variantRepository->findAllForGroup($command->familyHeadItemcode);
        $afasSamenstellingen = $this->afasRepository->findAllCanonical();

        if ($afasSamenstellingen === []) {
            throw new RuntimeException(
                'Lokale AFAS-snapshot is leeg. Draai eerst `bin/samenstellingen afas:pull`.',
            );
        }

        $matched = [];
        $notMatched = [];

        foreach ($variants as $variant) {
            if ($variant->id === null) {
                continue;
            }

            $expectedBom = $this->buildExpectedBom($variant);
            if ($expectedBom === []) {
                // Geen items aan de base toegevoegd → geen zinvolle match mogelijk.
                $this->variantRepository->markNoMatch($variant->id);
                $notMatched[] = $variant;
                continue;
            }

            $matchSku = $this->matcher->findMatch($variant->id, $expectedBom, $afasSamenstellingen);

            if ($matchSku !== null) {
                $this->variantRepository->markMatched($variant->id, $matchSku);
                $matched[] = ['variant' => $variant, 'afasItemcode' => $matchSku];
            } else {
                $this->variantRepository->markNoMatch($variant->id);
                $notMatched[] = $variant;
            }
        }

        return new SyncSummary(
            $command->familyHeadItemcode,
            count($afasSamenstellingen),
            $matched,
            $notMatched,
        );
    }

    /**
     * @return list<string>
     */
    private function buildExpectedBom(GroupVariant $variant): array
    {
        $items = $this->baseItemRepository->findAllForBase($variant->baseId);
        $bom = [];
        foreach ($items as $item) {
            $bom[] = $item->itemcode;
        }
        if ($variant->accessoireItemcode !== null) {
            $bom[] = $variant->accessoireItemcode;
        }

        return $bom;
    }
}
