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
        // AFAS dropt soms de connectie bij grote skips of zware connectors.
        // Drie pogingen met exponentiële backoff (1s, 3s, 9s) overbrugt dat zonder
        // de hele pull te laten falen.
        $attempts = 0;
        $lastException = null;
        while ($attempts < 3) {
            try {
                $t = microtime(true);
                $response = $this->http->request('GET', $this->baseUrl . '/connectors/' . $connectorId, [
                    'headers' => $this->headers(),
                    'query' => $query + ['take' => $take, 'skip' => $skip],
                ]);
                $dt = microtime(true) - $t;
                if ($attempts > 0 || $dt > 5.0) {
                    fwrite(STDERR, sprintf("[%s]   http %s skip=%d take=%d → %.1fs%s\n", date('H:i:s'), $connectorId, $skip, $take, $dt, $attempts > 0 ? ' (retry ' . $attempts . ')' : ''));
                }
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
            } catch (\GuzzleHttp\Exception\ConnectException | \GuzzleHttp\Exception\RequestException $e) {
                $lastException = $e;
                $attempts++;
                if ($attempts < 3) {
                    sleep(3 ** ($attempts - 1));
                }
            }
        }
        throw $lastException;
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

    /**
     * Haal metainfo van een UpdateConnector (lijst velden + toegestane enum-
     * waarden per veld). Gebruikt door de variant-write-flow om descriptions
     * uit PowerBI_Item (`"AED pakket"`) te vertalen naar de id's die de
     * UpdateConnector verwacht (`"08"`).
     *
     * @return array<string, mixed>
     */
    public function getMetainfoUpdate(string $connectorId): array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/metainfo/update/' . $connectorId, [
            'headers' => $this->headers(),
        ]);
        /** @var mixed $data */
        $data = json_decode($response->getBody()->getContents(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Update een bestaand record via een UpdateConnector (PUT).
     *
     * @param array<string, mixed> $payload Full body, bv:
     *   ['FbSalesPrice' => ['Element' => ['Fields' => [...]]]]
     */
    public function updateConnector(string $connectorId, array $payload): void
    {
        $this->http->request('PUT', $this->baseUrl . '/connectors/' . $connectorId, [
            'headers' => $this->headers() + ['Content-Type' => 'application/json'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Insert een nieuw record via een UpdateConnector (POST). PUT faalt met
     * "Prijs niet gevonden" als de rij nog niet bestaat — gebruik POST daarvoor.
     *
     * @param array<string, mixed> $payload
     */
    public function insertConnector(string $connectorId, array $payload): void
    {
        $this->http->request('POST', $this->baseUrl . '/connectors/' . $connectorId, [
            'headers' => $this->headers() + ['Content-Type' => 'application/json'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
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
