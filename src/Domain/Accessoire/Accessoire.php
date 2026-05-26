<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

use InvalidArgumentException;

final readonly class Accessoire
{
    public string $itemcode;
    public string $label;
    public int $deltaCents;

    public function __construct(string $itemcode, string $label, int $deltaCents = 0)
    {
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
    }
}
