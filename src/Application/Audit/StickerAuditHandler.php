<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Naming\StickerPolicy;

/**
 * Controleert per base of de stickerset in de AFAS-BOM matcht met de taal-code:
 *
 *   NL → 81111, FR → 81211, DK → 81411, DE → 81511, anders → 81611
 *
 * Voor compound talen (NL/FR/EN): eerste taal-token telt. Zie StickerPolicy.
 *
 * Een base wordt overgeslagen als er geen `afas_itemcode` of geen bijhorende
 * AFAS-samenstelling bestaat.
 */
final readonly class StickerAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private AfasSamenstellingenRepository $samenstellingen,
    ) {
    }

    /**
     * @return list<StickerDriftRow>
     */
    public function __invoke(AuditStickers $command): array
    {
        $rows = [];
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->afasItemcode === null) {
                    continue;
                }
                $samenstelling = $this->samenstellingen->findByItemcode($base->afasItemcode);
                if ($samenstelling === null) {
                    continue;
                }
                $expected = StickerPolicy::expectedSticker($base->languageCode);
                $actualStickers = array_values(array_filter(
                    $samenstelling->bomItemcodes,
                    static fn (string $code): bool => str_starts_with($code, '81'),
                ));
                if (in_array($expected, $actualStickers, true)) {
                    continue;
                }
                $rows[] = new StickerDriftRow(
                    groupName: $group->name,
                    familyHeadItemcode: $group->familyHeadItemcode,
                    baseName: $base->name,
                    baseAfasItemcode: $base->afasItemcode,
                    languageCode: $base->languageCode,
                    expectedSticker: $expected,
                    actualStickers: $actualStickers,
                );
            }
        }

        return $rows;
    }
}
