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
     * Lookup op `(group, afas_itemcode)` — leidend pad voor idempotente imports.
     * `null` wanneer er geen base met deze SKU in de groep zit.
     *
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     */
    public function findByAfasItemcodeInGroup(string $familyHeadItemcode, string $afasItemcode): ?GroupBase;

    /**
     * Alle bases met dit AFAS-itemcode (over groepen heen — normaal 1, maar
     * niet altijd uniek omdat enkele oude SKUs in meerdere groepen kunnen
     * voorkomen). Wordt gebruikt door publication-CLI's die het base-id niet
     * kennen maar wel de SKU.
     *
     * @return list<GroupBase>
     */
    public function findAllByAfasItemcode(string $afasItemcode): array;

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

    /**
     * Synchroniseer `name` met AFAS voor bases waar `afas_itemcode` matcht en
     * de huidige naam afwijkt. Bases zonder `afas_itemcode` blijven ongemoeid.
     * Retourneert het aantal daadwerkelijk gewijzigde rijen.
     *
     * @param array<int|string, string> $afasNameByItemcode itemcode → AFAS-naam (PHP cast numerieke string-keys naar int)
     */
    public function renameFromAfas(array $afasNameByItemcode): int;
}
