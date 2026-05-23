<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class Group
{
    public string $name;
    public string $familyHeadItemcode;
    public ?string $modelName;

    public function __construct(string $name, string $familyHeadItemcode, ?string $modelName = null)
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Groepsnaam mag niet leeg zijn.');
        }

        $trimmedItemcode = trim($familyHeadItemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Family-head itemcode mag niet leeg zijn.');
        }

        $trimmedModel = $modelName !== null ? trim($modelName) : null;

        $this->name = $trimmedName;
        $this->familyHeadItemcode = $trimmedItemcode;
        $this->modelName = ($trimmedModel === null || $trimmedModel === '') ? null : $trimmedModel;
    }
}
