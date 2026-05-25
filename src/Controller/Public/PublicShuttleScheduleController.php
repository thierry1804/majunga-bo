<?php

namespace App\Controller\Public;

use App\Repository\ShuttleScheduleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public', name: 'api_public_shuttle_')]
class PublicShuttleScheduleController extends AbstractController
{
    public function __construct(
        private ShuttleScheduleRepository $shuttleScheduleRepository,
        private PublicApiSerializer $serializer
    ) {
    }

    #[Route('/shuttle_schedules', name: 'shuttle_schedules', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $direction = $request->query->get('direction');

        if ($direction && in_array($direction, ['airport-to-city', 'city-to-airport'], true)) {
            $schedules = $this->shuttleScheduleRepository->findByDirection($direction);
        } else {
            $schedules = $this->shuttleScheduleRepository->findActiveSchedules();
        }

        $data = array_map(fn ($s) => $this->serializer->shuttleScheduleToArray($s), $schedules);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
