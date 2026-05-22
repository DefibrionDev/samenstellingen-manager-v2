<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBase
{
    public string $itemcode;
    public string $languageCode;
    public string $name;

    public function __construct(string $itemcode, string $languageCode, string $name)
    {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Base itemcode mag niet leeg zijn.');
        }

        $trimmedLanguage = trim($languageCode);
        if ($trimmedLanguage === '') {
            throw new InvalidArgumentException('Base taalcode mag niet leeg zijn.');
        }

        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Base naam mag niet leeg zijn.');
        }

        $this->itemcode = $trimmedItemcode;
        $this->languageCode = $trimmedLanguage;
        $this->name = $trimmedName;
    }
}
