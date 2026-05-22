<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use GuzzleHttp\ClientInterface;

final readonly class AfasHttpClient
{
    public function __construct(
        private ClientInterface $http,
        private string $baseUrl,
        private string $token,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return list<array<string, mixed>>
     */
    public function getConnectorPage(string $connectorId, array $query = [], int $take = 1000, int $skip = 0): array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/connectors/' . $connectorId, [
            'headers' => $this->headers(),
            'query' => $query + ['take' => $take, 'skip' => $skip],
        ]);

        /** @var mixed $data */
        $data = json_decode($response->getBody()->getContents(), true);
        if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) {
            return [];
        }

        $rows = [];
        foreach ($data['rows'] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return list<array<string, mixed>>
     */
    public function getConnectorAll(string $connectorId, array $query = [], int $pageSize = 1000): array
    {
        $rows = [];
        $skip = 0;
        do {
            $page = $this->getConnectorPage($connectorId, $query, $pageSize, $skip);
            foreach ($page as $row) {
                $rows[] = $row;
            }
            $skip += $pageSize;
        } while (count($page) === $pageSize);

        return $rows;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $tokenXml = '<token><version>1</version><data>' . $this->token . '</data></token>';

        return [
            'Authorization' => 'AfasToken ' . base64_encode($tokenXml),
            'Accept' => 'application/json',
        ];
    }
}
