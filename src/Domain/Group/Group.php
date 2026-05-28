<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class Group
{
    public string $name;
    public string $familyHeadItemcode;
    public ?string $modelNameNl;
    public ?string $modelNameFr;
    public ?string $modelNameEn;

    public function __construct(
        string $name,
        string $familyHeadItemcode,
        ?string $modelNameNl = null,
        ?string $modelNameFr = null,
        ?string $modelNameEn = null,
    ) {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Groepsnaam mag niet leeg zijn.');
        }

        $trimmedItemcode = trim($familyHeadItemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Family-head itemcode mag niet leeg zijn.');
        }

        $this->name = $trimmedName;
        $this->familyHeadItemcode = $trimmedItemcode;
        $this->modelNameNl = self::nullIfEmpty($modelNameNl);
        $this->modelNameFr = self::nullIfEmpty($modelNameFr);
        $this->modelNameEn = self::nullIfEmpty($modelNameEn);
    }

    /**
     * @param 'nl'|'fr'|'en' $taal
     */
    public function modelNameForTaal(string $taal): ?string
    {
        return match ($taal) {
            'nl' => $this->modelNameNl,
            'fr' => $this->modelNameFr,
            'en' => $this->modelNameEn,
        };
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
