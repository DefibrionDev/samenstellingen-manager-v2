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
