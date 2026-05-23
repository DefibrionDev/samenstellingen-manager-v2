<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Naming;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use RuntimeException;

/**
 * Bouwt de canonieke AFAS-samenstelling-naam volgens PLAN.md §9.1.
 *
 * Template:  {Prefix}: {ModelName} {Lang} {Inhoud}
 *
 * - {ModelName}  per groep (`group.model_name`), inclusief modeltype-keyword (`semi-automaat` etc.).
 * - {Lang}       kale taal-suffix (NL/FR/DE/DA/EN).
 * - {Inhoud}     `incl. safeset en stickerset` / `avec safeset et signalétique` / Engels-equivalent.
 *                Voor varianten met accessoire wordt {Inhoud} vervangen door `incl./avec {accessoire.label}`.
 *
 * Bij compound taal-codes (bv. `NL/FR` op een Lifepak-base) gebruiken we het eerste segment.
 */
final readonly class VariantNamingPolicy
{
    /** @var array<string, array{prefix: string, contentJoiner: string, contentTail: string}> */
    private const TEMPLATES = [
        'NL' => [
            'prefix' => 'AED pakket',
            'contentJoiner' => 'incl.',
            'contentTail' => 'safeset en stickerset',
        ],
        'FR' => [
            'prefix' => 'Pack DAE',
            'contentJoiner' => 'avec',
            'contentTail' => 'safeset et signalétique',
        ],
        'DE' => [
            'prefix' => 'AED package',
            'contentJoiner' => 'incl.',
            'contentTail' => 'safeset and stickerset',
        ],
        'DA' => [
            'prefix' => 'AED package',
            'contentJoiner' => 'incl.',
            'contentTail' => 'safeset and stickerset',
        ],
        'EN' => [
            'prefix' => 'AED package',
            'contentJoiner' => 'incl.',
            'contentTail' => 'safeset and stickerset',
        ],
    ];

    public function expectedName(Group $group, GroupBase $base, ?Accessoire $accessoire): string
    {
        if ($group->modelName === null) {
            throw new RuntimeException(sprintf(
                "Groep '%s' heeft geen model_name — vul die eerst voordat je de naam audit.",
                $group->familyHeadItemcode,
            ));
        }

        $lang = $this->resolveLanguage($base->languageCode);
        $template = self::TEMPLATES[$lang] ?? throw UnknownLanguageException::forCode($lang);

        $content = $accessoire !== null
            ? $accessoire->label
            : $template['contentTail'];

        return sprintf(
            '%s: %s %s %s %s',
            $template['prefix'],
            $group->modelName,
            $lang,
            $template['contentJoiner'],
            $content,
        );
    }

    private function resolveLanguage(string $code): string
    {
        // Lifepak CR2-bases hebben compound codes als 'NL/FR' of 'NL/EN'.
        // Volgens PLAN.md is taal-suffix kaal; we kiezen het eerste segment.
        $first = strtok($code, '/');

        return $first === false ? $code : trim($first);
    }
}
