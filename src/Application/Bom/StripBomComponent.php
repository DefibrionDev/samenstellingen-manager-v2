<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use InvalidArgumentException;

final readonly class StripBomComponent
{
    public string $bomItemcode;
    public bool $apply;
    public ?int $limit;
    /**
     * Scope: alleen samenstellingen strippen die óók een van deze componenten
     * bevatten (bv. 81111 strippen waar ook een anderstalige stickerset zit).
     * Leeg = alle samenstellingen met `bomItemcode`.
     *
     * @var list<string>
     */
    public array $onlyWith;

    /**
     * @param list<string> $onlyWith
     */
    public function __construct(string $bomItemcode, bool $apply = false, ?int $limit = null, array $onlyWith = [])
    {
        $code = trim($bomItemcode);
        if ($code === '') {
            throw new InvalidArgumentException('StripBomComponent.bomItemcode mag niet leeg zijn.');
        }
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('StripBomComponent.limit moet ≥1 zijn of null.');
        }

        $this->bomItemcode = $code;
        $this->apply = $apply;
        $this->limit = $limit;

        $only = [];
        foreach ($onlyWith as $code) {
            $code = trim($code);
            if ($code === '' || $code === $this->bomItemcode) {
                continue;
            }
            $only[] = $code;
        }
        $this->onlyWith = array_values(array_unique($only));
    }
}
