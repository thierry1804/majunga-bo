<?php

namespace App\Controller\Public;

use App\Repository\BookingRepository;
use App\Service\PayPalService;
use App\Service\PublicBookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/paypal', name: 'api_public_paypal_')]
class PayPalController extends AbstractController
{
    public function __construct(
        private PayPalService $payPalService,
        private BookingRepository $bookingRepository,
        private PublicBookingService $bookingService,
        private PublicApiSerializer $serializer
    ) {
    }

    #[Route('/create-order', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        if (!$this->payPalService->isConfigured()) {
            return new JsonResponse(['message' => 'PayPal non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $bookingId = $data['bookingId'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? 'EUR';

        if (!$bookingId || !$amount) {
            return new JsonResponse(['message' => 'bookingId et amount sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return new JsonResponse(['message' => 'Réservation non trouvée'], Response::HTTP_NOT_FOUND);
        }

        try {
            $order = $this->payPalService->createOrder((string) $amount, (string) $currency, $bookingId);

            return new JsonResponse($order, Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Erreur PayPal: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/capture-order', name: 'capture_order', methods: ['POST'])]
    public function captureOrder(Request $request): JsonResponse
    {
        if (!$this->payPalService->isConfigured()) {
            return new JsonResponse(['message' => 'PayPal non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $orderId = $data['orderId'] ?? null;
        $bookingId = $data['bookingId'] ?? null;

        if (!$orderId || !$bookingId) {
            return new JsonResponse(['message' => 'orderId et bookingId sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return new JsonResponse(['message' => 'Réservation non trouvée'], Response::HTTP_NOT_FOUND);
        }

        try {
            $capture = $this->payPalService->captureOrder($orderId);
            $this->bookingService->confirmBooking($booking, $capture['captureId']);

            return new JsonResponse([
                'capture' => $capture,
                'booking' => $this->serializer->bookingToArray($booking),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Erreur capture PayPal: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }
}
