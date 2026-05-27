<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use InvalidArgumentException;

final readonly class AfasPrijslijst
{
    public string $id;
    public string $omschrijving;

    public function __construct(string $id, string $omschrijving)
    {
        $id = trim($id);
        $omschrijving = trim($omschrijving);
        if ($id === '') {
            throw new InvalidArgumentException('AfasPrijslijst.id mag niet leeg zijn.');
        }
        if ($omschrijving === '') {
            throw new InvalidArgumentException('AfasPrijslijst.omschrijving mag niet leeg zijn.');
        }

        $this->id = $id;
        $this->omschrijving = $omschrijving;
    }
}
