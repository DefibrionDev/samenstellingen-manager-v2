<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Naming\UnknownLanguageException;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use RuntimeException;

/**
 * Zet de lokale base-namen (`group_bases.name`) van een groep naar de canonieke
 * template-naam ({@see VariantNamingPolicy}, accessoire = null). Raakt AFAS
 * niet aan — dit is de tool-lokale evenknie van `names:fix-drift`, bedoeld om
 * de doelnamen eerst in de manager te reviewen voordat AFAS hernoemd wordt.
 *
 * Let op: `afas:pull` spiegelt base-namen terug uit AFAS. Zolang AFAS zelf nog
 * de oude naam draagt, zet een volgende pull de lokale naam dus weer terug.
 *
 * Bases zonder afas_itemcode of waarvoor de template niet gerenderd kan worden
 * (ontbrekende model_name, onbekende taal) worden overgeslagen en gerapporteerd.
 */
final readonly class NormalizeBaseNamesHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private VariantNamingPolicy $policy,
    ) {
    }

    public function __invoke(NormalizeBaseNames $command): NormalizeBaseNamesResult
    {
        $renamed = [];
        $skipped = [];

        foreach ($command->familyHeadItemcodes as $familyHead) {
            $group = $this->groups->findByFamilyHeadItemcode($familyHead);
            if ($group === null) {
                $skipped[] = sprintf("Groep '%s' bestaat niet.", $familyHead);
                continue;
            }

            $nameByItemcode = [];
            foreach ($this->bases->findAllForGroup($familyHead) as $base) {
                if ($base->afasItemcode === null) {
                    $skipped[] = sprintf("Base '%s' (groep %s) heeft geen afas_itemcode.", $base->name, $familyHead);
                    continue;
                }

                try {
                    $expected = $this->policy->expectedName($group, $base, null);
                } catch (UnknownLanguageException|RuntimeException $e) {
                    $skipped[] = sprintf('Base %s: %s', $base->afasItemcode, $e->getMessage());
                    continue;
                }

                if ($expected === $base->name) {
                    continue;
                }

                $nameByItemcode[$base->afasItemcode] = $expected;
                $renamed[] = ['afasItemcode' => $base->afasItemcode, 'old' => $base->name, 'new' => $expected];
            }

            if ($nameByItemcode !== []) {
                $this->bases->renameFromAfas($nameByItemcode);
            }
        }

        return new NormalizeBaseNamesResult($renamed, $skipped);
    }
}
