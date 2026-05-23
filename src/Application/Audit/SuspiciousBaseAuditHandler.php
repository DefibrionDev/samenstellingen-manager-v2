<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;

/**
 * Detecteert AFAS-samenstellingen waarvan de SKU eindigt op een geregistreerde
 * accessoire-itemcode (`11683-60110`), maar wier BOM die accessoire **niet** bevat.
 *
 * Zo'n samenstelling ziet er semantisch uit als een variant (base + accessoire)
 * maar is in AFAS opgebouwd als een base — een typische data-inconsistentie waar
 * de portal-CSV-import overheen leest (die filtert op BOM-content, niet op SKU).
 *
 * Read-only audit, geen fix-actie hier.
 */
final readonly class SuspiciousBaseAuditHandler
{
    public function __construct(
        private AfasSamenstellingenRepository $afas,
        private AccessoireRepository $accessoires,
    ) {
    }

    /**
     * @return list<SuspiciousBaseRow>
     */
    public function __invoke(AuditSuspiciousBases $command): array
    {
        // Set van geregistreerde accessoire-itemcodes (van O(N) lookup naar O(1)).
        $accessoireByCode = [];
        foreach ($this->accessoires->findAll() as $accessoire) {
            $accessoireByCode[$accessoire->itemcode] = $accessoire;
        }

        $rows = [];
        foreach ($this->afas->findAllCanonical() as $samenstelling) {
            $itemcode = $samenstelling->itemcode;
            if (!str_contains($itemcode, '-')) {
                continue;
            }
            $parts = explode('-', $itemcode);
            $suffix = (string) end($parts);
            if (!isset($accessoireByCode[$suffix])) {
                continue;
            }
            if (in_array($suffix, $samenstelling->bomItemcodes, true)) {
                continue; // BOM bevat de accessoire wél — geen drift.
            }

            $rows[] = new SuspiciousBaseRow(
                afasItemcode: $itemcode,
                name: $samenstelling->name,
                expectedAccessoireItemcode: $suffix,
                expectedAccessoireLabel: $accessoireByCode[$suffix]->label,
                bom: $samenstelling->bomItemcodes,
            );
        }

        return $rows;
    }
}
