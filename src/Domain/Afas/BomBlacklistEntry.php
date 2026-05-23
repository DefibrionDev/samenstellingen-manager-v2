<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class BomBlacklistEntry
{
    public string $itemcode;
    public string $reason;

    public function __construct(string $itemcode, string $reason)
    {
        $trimmedItemcode = trim($itemcode);
        if ($trimmedItemcode === '') {
            throw new InvalidArgumentException('BOM-blacklist itemcode mag niet leeg zijn.');
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('BOM-blacklist reden mag niet leeg zijn.');
        }

        $this->itemcode = $trimmedItemcode;
        $this->reason = $trimmedReason;
    }
}
