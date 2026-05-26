<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class AfasPrijs
{
    public string $itemcode;
    public string $prijslijstId;
    public ?string $debiteurId;
    public int $verkoopprijsCents;
    public ?int $staffelAantal;
    public string $geldigVan;
    public ?string $geldigTot;

    public function __construct(
        string $itemcode,
        string $prijslijstId,
        ?string $debiteurId,
        int $verkoopprijsCents,
        ?int $staffelAantal,
        string $geldigVan,
        ?string $geldigTot,
    ) {
        $itemcode = trim($itemcode);
        $prijslijstId = trim($prijslijstId);
        if ($itemcode === '') {
            throw new InvalidArgumentException('AfasPrijs.itemcode mag niet leeg zijn.');
        }
        if ($prijslijstId === '') {
            throw new InvalidArgumentException('AfasPrijs.prijslijstId mag niet leeg zijn.');
        }
        if ($verkoopprijsCents < 0) {
            throw new InvalidArgumentException('AfasPrijs.verkoopprijsCents mag niet negatief zijn.');
        }

        $debiteur = $debiteurId !== null ? trim($debiteurId) : null;
        $van = trim($geldigVan);
        $tot = $geldigTot !== null ? trim($geldigTot) : null;
        if ($van === '') {
            throw new InvalidArgumentException('AfasPrijs.geldigVan mag niet leeg zijn.');
        }

        $this->itemcode = $itemcode;
        $this->prijslijstId = $prijslijstId;
        $this->debiteurId = ($debiteur === null || $debiteur === '') ? null : $debiteur;
        $this->verkoopprijsCents = $verkoopprijsCents;
        $this->staffelAantal = $staffelAantal;
        $this->geldigVan = $van;
        $this->geldigTot = ($tot === null || $tot === '') ? null : $tot;
    }
}
