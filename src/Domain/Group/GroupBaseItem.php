<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use InvalidArgumentException;

final readonly class GroupBaseItem
{
    public string $itemcode;
    public string $name;

    public function __construct(string $itemcode, string $name)
    {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('Itemcode mag niet leeg zijn.');
        }

        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Item-naam mag niet leeg zijn.');
        }

        $this->itemcode = $trimmedItemcode;
        $this->name = $trimmedName;
    }
}
