<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The store's half of PRD §6.3's pairing exchange.
 *
 * The direction matters: the store calls out to Ecommerly, carrying a
 * short-lived single-use token the merchant pasted in. Ecommerly
 * never calls in to establish the connection, so a store behind a
 * firewall or without a public hostname can still be paired, and
 * there is no inbound endpoint accepting anything but an
 * already-signed request.
 *
 * The token is the only credential this exchange presents, and it is
 * spent on success — a second attempt with the same token fails by
 * design.
 */
class PairingService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConnectionConfig $config
    ) {
    }

    public function pair(string $pairingToken): PairingResult
    {
        $baseUrl = $this->config->ecommerlyBaseUrl();

        if ($baseUrl === null) {
            return PairingResult::failed('Set the Ecommerly base URL in the plugin configuration before pairing.');
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/api/v1/connections/pair', [
                'json' => ['pairing_token' => $pairingToken],
                'timeout' => 15,
            ]);

            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            return PairingResult::failed('Could not reach Ecommerly at '.$baseUrl.'.');
        }

        if (! isset($payload['connection_id'], $payload['connection_secret'])) {
            return PairingResult::failed(
                is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : 'Ecommerly rejected the pairing token.'
            );
        }

        $scopes = isset($payload['scopes']) && is_array($payload['scopes'])
            ? array_values(array_filter($payload['scopes'], 'is_string'))
            : [];

        $this->config->storePairing(
            (string) $payload['connection_id'],
            (string) $payload['connection_secret'],
            $scopes
        );

        return PairingResult::paired((string) $payload['connection_id']);
    }
}
