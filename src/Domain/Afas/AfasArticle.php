<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class AfasArticle
{
    public string $itemcode;
    public string $name;

    public function __construct(string $itemcode, string $name)
    {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('AFAS-artikel itemcode mag niet leeg zijn.');
        }

        $this->itemcode = $trimmedItemcode;
        $this->name = trim($name);
    }
}
