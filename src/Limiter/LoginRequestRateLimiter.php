<?php

namespace App\Limiter;

use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class LoginRequestRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        private RateLimiterFactory $factory
    ) {
    }

    public function consume(Request $request): RateLimit
    {
        // Utiliser l'IP et l'email comme clé pour le rate limiting
        $key = $this->getKey($request);
        $limiter = $this->factory->create($key);
        
        return $limiter->consume();
    }

    public function reset(Request $request): void
    {
        $key = $this->getKey($request);
        $limiter = $this->factory->create($key);
        $limiter->reset();
    }

    private function getKey(Request $request): string
    {
        // Utiliser l'IP de la requête comme clé
        // Si un email est fourni dans la requête, on peut l'utiliser aussi
        $ip = $request->getClientIp();
        $email = null;
        
        // Essayer d'extraire l'email depuis les paramètres de la requête
        if ($request->request->has('email')) {
            $email = $request->request->get('email');
        } elseif ($request->getContent()) {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;
        }
        
        if ($email) {
            return sprintf('login_%s_%s', $ip, hash('sha256', $email));
        }
        
        return sprintf('login_%s', $ip);
    }
}

