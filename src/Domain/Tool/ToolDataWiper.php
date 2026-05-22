<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Tool;

interface ToolDataWiper
{
    /**
     * Wis alle tool-data (groups, bases, items, accessoires, links, variants) maar laat
     * de AFAS-snapshot intact. Bedoeld om vóór een herimport een schone staat te garanderen.
     */
    public function wipe(): void;
}
