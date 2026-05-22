<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

use InvalidArgumentException;

final readonly class Accessoire
{
    public string $itemcode;
    public string $label;

    public function __construct(string $itemcode, string $label)
    {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Accessoire itemcode mag niet leeg zijn.');
        }

        $trimmedLabel = trim($label);
        if ($trimmedLabel === '') {
            throw new InvalidArgumentException('Accessoire label mag niet leeg zijn.');
        }

        $this->itemcode = $trimmedItemcode;
        $this->label = $trimmedLabel;
    }
}
