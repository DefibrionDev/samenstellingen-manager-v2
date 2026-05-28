<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use InvalidArgumentException;

/**
 * Beschrijft één toe-te-passen prijs-correctie: variant X in prijslijst Y op
 * staffel Z moet `targetCents` worden (van `currentCents`). Begindatum behouden
 * we van de bestaande rij zodat we geen prijshistorie corrumperen.
 */
final readonly class PriceFixPlan
{
    public string $variantItemcode;
    public string $prijslijstId;
    public ?int $staffelAantal;
    public int $currentCents;
    public int $targetCents;
    public string $beginDate;

    public function __construct(
        string $variantItemcode,
        string $prijslijstId,
        ?int $staffelAantal,
        int $currentCents,
        int $targetCents,
        string $beginDate,
    ) {
        if (trim($variantItemcode) === '') {
            throw new InvalidArgumentException('PriceFixPlan.variantItemcode mag niet leeg zijn.');
        }
        if (trim($prijslijstId) === '') {
            throw new InvalidArgumentException('PriceFixPlan.prijslijstId mag niet leeg zijn.');
        }
        if ($targetCents < 0) {
            throw new InvalidArgumentException('PriceFixPlan.targetCents mag niet negatief zijn.');
        }
        if (trim($beginDate) === '') {
            throw new InvalidArgumentException('PriceFixPlan.beginDate mag niet leeg zijn.');
        }

        $this->variantItemcode = trim($variantItemcode);
        $this->prijslijstId = trim($prijslijstId);
        $this->staffelAantal = $staffelAantal;
        $this->currentCents = $currentCents;
        $this->targetCents = $targetCents;
        $this->beginDate = trim($beginDate);
    }

    public function differenceCents(): int
    {
        return $this->targetCents - $this->currentCents;
    }
}
