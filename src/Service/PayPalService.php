<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PayPalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;

    public function __construct(
        private HttpClientInterface $httpClient,
        ?string $clientId,
        ?string $clientSecret,
        ?string $mode,
        ?string $webhookId
    ) {
        $this->clientId = $clientId ?? '';
        $this->clientSecret = $clientSecret ?? '';
        $this->webhookId = $webhookId ?? '';
        $mode = ($mode ?? '') !== '' ? $mode : 'sandbox';
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * @return array{orderId: string, status: string}
     */
    public function createOrder(string $amount, string $currency, string $bookingId): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $bookingId,
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => number_format((float) $amount, 2, '.', ''),
                    ],
                ]],
            ],
        ]);

        $data = $response->toArray(false);

        return [
            'orderId' => $data['id'] ?? '',
            'status' => $data['status'] ?? '',
        ];
    }

    /**
     * @return array{captureId: string, status: string}
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);
        $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? [];

        return [
            'captureId' => $capture['id'] ?? $orderId,
            'status' => $data['status'] ?? '',
        ];
    }

    private function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        $data = $response->toArray(false);

        return $data['access_token'] ?? '';
    }
}
