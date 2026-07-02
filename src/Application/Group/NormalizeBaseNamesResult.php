<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class NormalizeBaseNamesResult
{
    /**
     * @param list<array{afasItemcode: string, old: string, new: string}> $renamed
     * @param list<string>                                                $skipped Redenen waarom een groep/base is overgeslagen.
     */
    public function __construct(
        public array $renamed,
        public array $skipped,
    ) {
    }
}
