<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Bom\BomLineReader;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Naming\StickerPolicy;

/**
 * Herstel ontbrekende stickersets per base. Bron-van-waarheid voor "welke
 * sticker hoort waar" is `StickerPolicy` + de base-taal — niet een
 * gepersisteerde lijst. Werkt symmetrisch met `bom:strip-component`:
 * tool-side `group_base_items` insert + AFAS PUT FbComposition met @Action=insert.
 *
 * `--language=<code>` filtert op base-taal zodat we één stickerset tegelijk
 * kunnen terugzetten (bv. alleen `EN` → 81611).
 */
final readonly class RestoreStickersHandler
{
    private const STICKER_LABEL = 'AED stickerset';
    private const STICKER_VAIT = 'Sam';

    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupBaseItemRepository $baseItems,
        private AfasSamenstellingenRepository $afasSamenstellingen,
        private BomLineReader $bomLineReader,
        private BomComponentRestoreWriter $writer,
    ) {
    }

    public function __invoke(RestoreStickers $command): RestoreStickersResult
    {
        // Eén bulk-pull naar AFAS — onze dry-run-tabel wijst hierdoor de echte
        // PrSe-waarde aan, niet een misleidende default. ~1 HTTP-call.
        $maxPrSeBySamenstelling = $this->bomLineReader->findMaxPrSePerSamenstelling();

        $afasByItemcode = [];
        foreach ($this->afasSamenstellingen->findAll() as $s) {
            $afasByItemcode[$s->itemcode] = $s;
        }

        $toolInserts = [];
        $afasPlans = [];
        // Alle base-itemcodes: een NL-base zonder taal-suffix (10144) mag de
        // taal-bases 10144-UK/-DE/… en hún varianten niet als eigen variant zien
        // (bug 9 sep 2026: 81111 in ~480 anderstalige BOMs).
        $allBaseCodes = $this->bases->findAllAfasItemcodes();
        $filterLang = $command->languageCode !== null ? strtoupper(trim($command->languageCode)) : null;

        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->id === null) {
                    continue;
                }
                if ($filterLang !== null && strtoupper(explode('/', trim($base->languageCode))[0]) !== $filterLang) {
                    continue;
                }
                $expectedSticker = StickerPolicy::expectedSticker($base->languageCode);

                $hasInTool = false;
                foreach ($this->baseItems->findAllForBase($base->id) as $item) {
                    if ($item->itemcode === $expectedSticker) {
                        $hasInTool = true;
                        break;
                    }
                }
                if (!$hasInTool) {
                    $toolInserts[] = [
                        'baseId' => $base->id,
                        'baseAfasItemcode' => $base->afasItemcode,
                        'languageCode' => $base->languageCode,
                        'sticker' => $expectedSticker,
                    ];
                }

                if ($base->afasItemcode === null) {
                    continue;
                }

                foreach ($this->collectVariants($base->afasItemcode, $afasByItemcode, $allBaseCodes) as $itemcode) {
                    $samenstelling = $afasByItemcode[$itemcode];
                    if (in_array($expectedSticker, $samenstelling->bomItemcodes, true)) {
                        continue;
                    }
                    $nextPrSe = ($maxPrSeBySamenstelling[$itemcode] ?? 0) + 10;
                    $afasPlans[] = new BomComponentRestorePlan(
                        $itemcode,
                        $expectedSticker,
                        self::STICKER_VAIT,
                        $nextPrSe,
                    );
                    if ($command->limit !== null && count($afasPlans) >= $command->limit) {
                        break 3;
                    }
                }
            }
        }

        if (!$command->apply) {
            return new RestoreStickersResult($toolInserts, $afasPlans, 0, 0, []);
        }

        $toolInserted = 0;
        foreach ($toolInserts as $insert) {
            try {
                $this->baseItems->saveForBase(
                    $insert['baseId'],
                    new GroupBaseItem($insert['sticker'], self::STICKER_LABEL),
                );
                ++$toolInserted;
            } catch (\Throwable) {
                // Item bestaat al / base onvindbaar → ga door; AFAS-pad is wat telt.
            }
        }

        $afasApplied = 0;
        $failures = [];
        foreach ($afasPlans as $plan) {
            try {
                $this->writer->apply($plan);
                ++$afasApplied;
            } catch (BomComponentRestoreFailedException $e) {
                $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
            }
        }

        return new RestoreStickersResult($toolInserts, $afasPlans, $toolInserted, $afasApplied, $failures);
    }

    /**
     * @param array<string, \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling> $afasByItemcode
     * @param list<string> $allBaseCodes
     * @return list<string>
     */
    private function collectVariants(string $baseSku, array $afasByItemcode, array $allBaseCodes): array
    {
        $result = [];
        $prefix = $baseSku . '-';
        // andere bases die onder deze prefix vallen (10144-UK onder 10144-): hun
        // codes en varianten horen niet bij deze base
        $otherBases = array_values(array_filter(
            $allBaseCodes,
            static fn (string $c): bool => $c !== $baseSku && str_starts_with($c, $prefix),
        ));
        foreach ($afasByItemcode as $itemcode => $_) {
            $code = (string) $itemcode;
            if ($code !== $baseSku && !str_starts_with($code, $prefix)) {
                continue;
            }
            foreach ($otherBases as $other) {
                if ($code === $other || str_starts_with($code, $other . '-')) {
                    continue 2;
                }
            }
            $result[] = $code;
        }
        sort($result);

        return $result;
    }
}
