<?php

namespace App\Controller\Public;

use App\Repository\TourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public', name: 'api_public_')]
class PublicTourController extends AbstractController
{
    public function __construct(
        private TourRepository $tourRepository,
        private PublicApiSerializer $serializer
    ) {
    }

    #[Route('/tours', name: 'tours', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tours = $this->tourRepository->findActiveTours();
        $data = array_map(fn ($tour) => $this->serializer->tourToArray($tour), $tours);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
