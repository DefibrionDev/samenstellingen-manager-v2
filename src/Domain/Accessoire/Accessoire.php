<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

use InvalidArgumentException;

final readonly class Accessoire
{
    public string $itemcode;
    public string $label;
    public int $deltaCents;
    public ?string $naamKortNl;
    public ?string $naamKortFr;
    public ?string $naamKortEn;
    public ?string $naamKortDe;

    public function __construct(
        string $itemcode,
        string $label,
        int $deltaCents = 0,
        ?string $naamKortNl = null,
        ?string $naamKortFr = null,
        ?string $naamKortEn = null,
        ?string $naamKortDe = null,
    ) {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Accessoire itemcode mag niet leeg zijn.');
        }

        $trimmedLabel = trim($label);
        if ($trimmedLabel === '') {
            throw new InvalidArgumentException('Accessoire label mag niet leeg zijn.');
        }

        if ($deltaCents < 0) {
            throw new InvalidArgumentException(sprintf(
                'Accessoire delta mag niet negatief zijn (%d cents). Negatieve toeslag = korting; later apart modelleren.',
                $deltaCents,
            ));
        }

        $this->itemcode = $trimmedItemcode;
        $this->label = $trimmedLabel;
        $this->deltaCents = $deltaCents;
        $this->naamKortNl = self::nullIfEmpty($naamKortNl);
        $this->naamKortFr = self::nullIfEmpty($naamKortFr);
        $this->naamKortEn = self::nullIfEmpty($naamKortEn);
        $this->naamKortDe = self::nullIfEmpty($naamKortDe);
    }

    /**
     * @param 'nl'|'fr'|'en'|'de' $taal
     */
    public function naamKort(string $taal): ?string
    {
        return match ($taal) {
            'nl' => $this->naamKortNl,
            'fr' => $this->naamKortFr,
            'en' => $this->naamKortEn,
            'de' => $this->naamKortDe,
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
