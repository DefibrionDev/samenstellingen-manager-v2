<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;

/**
 * Lijst AFAS-samenstellingen die geen CBS-goederencode hebben gevuld.
 * Wordt gebruikt om referentie-samenstellingen te identificeren die
 * `variants:fix-missing --apply` zouden blokkeren (zonder CBS kan
 * de POST naar FbComposition niet slagen).
 */
final readonly class MissingCbsAuditHandler
{
    public function __construct(
        private AfasSamenstellingenRepository $repository,
    ) {
    }

    /**
     * @return list<MissingCbsRow>
     */
    public function __invoke(AuditMissingCbs $command): array
    {
        $rows = [];
        foreach ($this->repository->findAllCanonical() as $samenstelling) {
            if ($samenstelling->cbsCode !== null && $samenstelling->cbsCode !== '') {
                continue;
            }
            $rows[] = new MissingCbsRow(
                $samenstelling->itemcode,
                $samenstelling->name,
                $samenstelling->itemcodeParent,
            );
        }

        return $rows;
    }
}
