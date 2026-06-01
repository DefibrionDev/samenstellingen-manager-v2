<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Eén te-PUT'en flag-update op een AFAS-samenstelling (base of accessoire-
 * variant). De flag-map bevat alle relevante free-field UUIDs (Sync_* en
 * Tonen_* paren per website), elk op true (gepubliceerd) of false.
 */
final readonly class PublicationSyncPlan
{
    /**
     * @param array<string, bool> $freeFieldFlags
     */
    public function __construct(
        public string $afasItemcode,
        public string $baseAfasItemcode,
        public array $freeFieldFlags,
    ) {
    }
}
