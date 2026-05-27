<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class PrijslijstBlacklistEntry
{
    public string $prijslijstId;
    public string $reden;
    public ?string $aangemaaktOp;

    public function __construct(string $prijslijstId, string $reden, ?string $aangemaaktOp = null)
    {
        $prijslijstId = trim($prijslijstId);
        $reden = trim($reden);
        if ($prijslijstId === '') {
            throw new InvalidArgumentException('Prijslijst-blacklist prijslijstId mag niet leeg zijn.');
        }
        if ($reden === '') {
            throw new InvalidArgumentException('Prijslijst-blacklist reden mag niet leeg zijn.');
        }

        $this->prijslijstId = $prijslijstId;
        $this->reden = $reden;
        $this->aangemaaktOp = $aangemaaktOp;
    }
}
