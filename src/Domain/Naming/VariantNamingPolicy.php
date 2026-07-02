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
 * Vier taal-templates op basis van het eerste taal-token van de base;
 * alles buiten NL/FR/DE valt terug op de Engelse template:
 *
 *   NL:      AED Pakket: {ModelNL} ({LangSuffix}) met {AccessoireNL}
 *   FR:      Pack DAE: {ModelFR} ({LangSuffix}) avec {AccessoireFR}
 *   DE:      AED Paket: {ModelDE} ({LangSuffix}) mit {AccessoireDE}
 *   Anders:  AED Package: {ModelEN} ({LangSuffix}) with {AccessoireEN}
 *
 * LangSuffix: de taal-tokens zelf, ongewijzigd (NL → NL, EN → EN),
 *   in de naam altijd tussen haakjes; compound `NL/FR` → `(NL-FR)`,
 *   `NL/EN/FR` → `(NL-EN-FR)`, `SE/EN/NO` → `(SE-EN-NO)`.
 *
 * Per-taal naam-velden:
 *   - groep `model_name_{nl|fr|en|de}`
 *   - accessoire `naam_kort_{nl|fr|en|de}`
 *
 * AFAS' Omschrijving-veld (FbItemArticle.Ds) is maximaal 100 tekens. Namen die
 * daar overheen gaan worden ingekort met een zichtbare ellipsis (…) in het
 * MODEL-deel; variant-label, taalset en accessoire blijven volledig staan zodat
 * het onderscheidende deel leesbaar en de naam uniek blijft. Namen die passen
 * worden nooit aangeraakt.
 *
 * Falt netjes met een uitvoerbare CLI-suggestie als een veld nog leeg is.
 */
final readonly class VariantNamingPolicy
{
    private const MAX_NAME_LENGTH = 100; // AFAS FbItemArticle 'Omschrijving' (Ds)

    private const TEMPLATES = [
        'nl' => ['prefix' => 'AED Pakket:', 'connector' => 'met'],
        'fr' => ['prefix' => 'Pack DAE:', 'connector' => 'avec'],
        'de' => ['prefix' => 'AED Paket:', 'connector' => 'mit'],
        'en' => ['prefix' => 'AED Package:', 'connector' => 'with'],
    ];

    public function expectedName(Group $group, GroupBase $base, ?Accessoire $accessoire): string
    {
        $langSuffix = $this->langSuffix($base->languageCode);
        $taalBucket = match ($this->firstLanguageToken($base->languageCode)) {
            'NL' => 'nl',
            'FR' => 'fr',
            'DE' => 'de',
            default => 'en',
        };
        ['prefix' => $prefix, 'connector' => $connector] = self::TEMPLATES[$taalBucket];

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

        $accessoireNaam = null;
        if ($accessoire !== null) {
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
        }

        $name = $this->render($prefix, $modelName, $base->variantLabel, $langSuffix, $connector, $accessoireNaam);
        if (mb_strlen($name) <= self::MAX_NAME_LENGTH) {
            return $name;
        }

        // Te lang voor AFAS: alleen het model-deel inkorten met zichtbare "…".
        $budget = mb_strlen($modelName) - (mb_strlen($name) - self::MAX_NAME_LENGTH) - 1;
        if ($budget < 1) {
            // Extreem geval: zelfs zonder model past 'ie niet — hard afkappen.
            return rtrim(mb_substr($name, 0, self::MAX_NAME_LENGTH - 1)) . '…';
        }
        $ingekortModel = rtrim(mb_substr($modelName, 0, $budget)) . '…';

        return $this->render($prefix, $ingekortModel, $base->variantLabel, $langSuffix, $connector, $accessoireNaam);
    }

    private function render(
        string $prefix,
        string $modelName,
        ?string $variantLabel,
        string $langSuffix,
        string $connector,
        ?string $accessoireNaam,
    ): string {
        $modelWithLabel = $variantLabel !== null
            ? sprintf('%s %s', $modelName, $variantLabel)
            : $modelName;

        return $accessoireNaam === null
            ? sprintf('%s %s (%s)', $prefix, $modelWithLabel, $langSuffix)
            : sprintf('%s %s (%s) %s %s', $prefix, $modelWithLabel, $langSuffix, $connector, $accessoireNaam);
    }

    private function firstLanguageToken(string $languageCode): string
    {
        return strtoupper(explode('/', trim($languageCode))[0]);
    }

    private function langSuffix(string $languageCode): string
    {
        $tokens = array_map(
            static fn (string $token): string => strtoupper(trim($token)),
            explode('/', trim($languageCode)),
        );
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            throw UnknownLanguageException::forCode($languageCode);
        }

        return implode('-', $tokens);
    }
}
