<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupBaseRepository
{
    /**
     * @throws GroupNotFoundException     wanneer de groep niet bestaat.
     * @throws BaseAlreadyExistsException wanneer een base met deze naam al in deze groep zit.
     */
    public function saveForGroup(string $familyHeadItemcode, GroupBase $base): GroupBase;

    public function findById(int $baseId): ?GroupBase;

    /**
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     *
     * @return list<GroupBase>
     */
    public function findAllForGroup(string $familyHeadItemcode): array;

    /**
     * Alle niet-lege afas_itemcode-waarden over álle bases heen. Gebruikt door de
     * portal-CSV-import om handmatig vastgezette bases voorrang te geven bij
     * ambigue article-codes.
     *
     * @return list<string>
     */
    public function findAllAfasItemcodes(): array;

    /**
     * Verwijder een base. Cascade ruimt group_base_items + group_variants op.
     * Idempotent: onbekende id is no-op.
     */
    public function delete(int $baseId): void;

    /**
     * Family-head-itemcode van de groep waar deze base bij hoort (null als de
     * base niet bestaat). Nodig voor delete-flows die daarna de variant-matrix
     * voor die groep moeten regenereren.
     */
    public function findFamilyHeadForBase(int $baseId): ?string;

    /**
     * Zet (of wis met `null`) het variant_label op alle bases met dit
     * afas_itemcode. Retourneert het aantal aangepaste rijen — 0 betekent dat
     * de itemcode niet voorkomt en de caller een fout kan rapporteren.
     */
    public function setVariantLabelByAfasItemcode(string $afasItemcode, ?string $variantLabel): int;

    /**
     * Wijzig de language_code van alle bases met dit afas_itemcode. Lege
     * string is niet toegestaan (taal is verplicht — gebruik delete + re-add
     * als je 'm echt leeg wilt).
     *
     * @throws \InvalidArgumentException als de taal-code leeg is.
     */
    public function setLanguageCodeByAfasItemcode(string $afasItemcode, string $languageCode): int;
}
