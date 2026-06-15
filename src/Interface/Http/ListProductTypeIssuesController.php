<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditProductType;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeAuditHandler;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Read-only: lijst van samenstellingen met ontbrekende/afwijkende producttypes.
 * Conform de UI-conventie geen mutatie-endpoint — per rij een `cliHint` met het
 * commando dat de fix uitvoert. Zie PLAN-AFAS.md §35.
 */
final readonly class ListProductTypeIssuesController
{
    public function __construct(private ProductTypeAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditProductType()) as $row) {
            $payload[] = [
                'afasItemcode' => $row->afasItemcode,
                'issueType' => $row->issueType->value,
                'baseItemcode' => $row->baseItemcode,
                'current01' => $row->current01,
                'current02' => $row->current02,
                'expected01' => $row->expected01,
                'expected02' => $row->expected02,
                'groupName' => $row->groupName,
                'cliHint' => $this->cliHint($row->issueType, $row->baseItemcode),
            ];
        }

        return Json::write($response, $payload);
    }

    private function cliHint(ProductTypeIssueType $type, string $baseItemcode): string
    {
        return match ($type) {
            ProductTypeIssueType::BaseEmpty => sprintf('Vul Product_type 01/02 op %s in AFAS.', $baseItemcode),
            ProductTypeIssueType::VariantFixable => 'Draai `producttype:fix-variants --apply`.',
            ProductTypeIssueType::VariantBlocked => sprintf('Vul eerst base %s in AFAS, draai daarna `producttype:fix-variants --apply`.', $baseItemcode),
        };
    }
}
