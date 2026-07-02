<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class NormalizeBaseNames
{
    /**
     * @param list<string> $familyHeadItemcodes Groepen (family-head) waarvan de base-namen genormaliseerd worden.
     */
    public function __construct(
        public array $familyHeadItemcodes,
    ) {
    }
}
