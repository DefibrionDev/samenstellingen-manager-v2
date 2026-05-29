<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use InvalidArgumentException;

/**
 * Eén toe-te-passen naam-correctie: AFAS-artikel X krijgt nieuwe Ds-waarde.
 */
final readonly class NameFixPlan
{
    public string $afasItemcode;
    public string $currentName;
    public string $targetName;

    public function __construct(string $afasItemcode, string $currentName, string $targetName)
    {
        $code = trim($afasItemcode);
        if ($code === '') {
            throw new InvalidArgumentException('NameFixPlan.afasItemcode mag niet leeg zijn.');
        }
        $target = trim($targetName);
        if ($target === '') {
            throw new InvalidArgumentException('NameFixPlan.targetName mag niet leeg zijn.');
        }

        $this->afasItemcode = $code;
        $this->currentName = $currentName;
        $this->targetName = $target;
    }
}
