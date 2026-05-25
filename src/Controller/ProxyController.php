<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/public', name: 'api_public_proxy_')]
class ProxyController extends AbstractController
{
    private readonly string $openWeatherApiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        ?string $openWeatherApiKey = null,
    ) {
        $this->openWeatherApiKey = $openWeatherApiKey ?? '';
    }

    #[Route('/weather', name: 'weather', methods: ['GET'])]
    public function weather(Request $request): JsonResponse
    {
        if ($this->openWeatherApiKey === '') {
            return new JsonResponse(['message' => 'Service météo non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $city = $request->query->get('city', 'Majunga');
        $lang = $request->query->get('lang', 'fr');

        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'q' => $city,
                    'appid' => $this->openWeatherApiKey,
                    'units' => 'metric',
                    'lang' => $lang,
                ],
            ]);

            return new JsonResponse($response->toArray(false), Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Erreur météo: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }
}
