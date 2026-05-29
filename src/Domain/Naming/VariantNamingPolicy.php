<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Naming;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use RuntimeException;

/**
 * Canonieke AFAS-samenstelling-naam volgens PLAN.md §18.
 *
 * Twee templates op basis van het eerste taal-token van de base:
 *
 *   Zuiver FR:    Pack DAE: {ModelFR} {LangSuffix} avec {AccessoireFR}
 *   Anders:       AED Pakket: {ModelNL} {LangSuffix} met {AccessoireNL}
 *
 * LangSuffix:
 *   NL → NL, FR → FR, DE → DE, DK → DK, EN → UK, WAL → WAL,
 *   compound `NL/FR` → `NL-FR`, `NL/EN/FR` → `NL-EN-FR`.
 *
 * Per-taal naam-velden:
 *   - groep `model_name_{nl|fr}` (en `_en` als toekomstige uitbreiding)
 *   - accessoire `naam_kort_{nl|fr}`
 *
 * Falt netjes met een uitvoerbare CLI-suggestie als een veld nog leeg is.
 */
final readonly class VariantNamingPolicy
{
    private const LANG_SUFFIX_OVERRIDES = [
        'EN' => 'UK',
    ];

    public function expectedName(Group $group, GroupBase $base, ?Accessoire $accessoire): string
    {
        $firstToken = $this->firstLanguageToken($base->languageCode);
        $langSuffix = $this->langSuffix($base->languageCode);
        $taalBucket = $firstToken === 'FR' ? 'fr' : 'nl';

        $modelName = $group->modelNameForTaal($taalBucket);
        if ($modelName === null) {
            throw new RuntimeException(sprintf(
                "Groep '%s' mist model_name_%s — vul via: `bin/samenstellingen group:set-model-naam %s %s '<naam>'`",
                $group->familyHeadItemcode,
                $taalBucket,
                $group->familyHeadItemcode,
                $taalBucket,
            ));
        }

        $modelWithLabel = $base->variantLabel !== null
            ? sprintf('%s %s', $modelName, $base->variantLabel)
            : $modelName;

        if ($accessoire === null) {
            return $taalBucket === 'fr'
                ? sprintf('Pack DAE: %s %s', $modelWithLabel, $langSuffix)
                : sprintf('AED Pakket: %s %s', $modelWithLabel, $langSuffix);
        }

        $accessoireNaam = $accessoire->naamKort($taalBucket);
        if ($accessoireNaam === null) {
            throw new RuntimeException(sprintf(
                "Accessoire '%s' mist naam_kort_%s — vul via: `bin/samenstellingen accessoire:set-naam-kort %s %s '<naam>'`",
                $accessoire->itemcode,
                $taalBucket,
                $accessoire->itemcode,
                $taalBucket,
            ));
        }

        return $taalBucket === 'fr'
            ? sprintf('Pack DAE: %s %s avec %s', $modelWithLabel, $langSuffix, $accessoireNaam)
            : sprintf('AED Pakket: %s %s met %s', $modelWithLabel, $langSuffix, $accessoireNaam);
    }

    private function firstLanguageToken(string $languageCode): string
    {
        $first = strtoupper(explode('/', trim($languageCode))[0]);

        return $first;
    }

    private function langSuffix(string $languageCode): string
    {
        $tokens = array_map(
            fn (string $token): string => $this->mapToken(strtoupper(trim($token))),
            explode('/', trim($languageCode)),
        );
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            throw UnknownLanguageException::forCode($languageCode);
        }

        return implode('-', $tokens);
    }

    private function mapToken(string $token): string
    {
        return self::LANG_SUFFIX_OVERRIDES[$token] ?? $token;
    }
}
