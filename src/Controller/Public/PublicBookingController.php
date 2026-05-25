<?php

namespace App\Controller\Public;

use App\Service\PublicBookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public', name: 'api_public_booking_')]
class PublicBookingController extends AbstractController
{
    public function __construct(
        private PublicBookingService $bookingService,
        private PublicApiSerializer $serializer,
        private RateLimiterFactory $publicBookingLimiter
    ) {
    }

    #[Route('/bookings', name: 'bookings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $limiter = $this->publicBookingLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(
                ['message' => 'Trop de requêtes. Réessayez plus tard.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $booking = $this->bookingService->createFromPublicRequest($data);

            return new JsonResponse(
                $this->serializer->bookingToArray($booking),
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
