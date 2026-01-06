<?php

namespace App\Controller;

use App\Entity\Tour;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tours', name: 'api_tours_')]
class TourController extends AbstractController
{
    public function __construct(
        private TourRepository $tourRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Ajoute une ou plusieurs URLs d'images à un tour existant
     */
    #[Route('/{id}/images', name: 'add_images', methods: ['POST'])]
    public function addImages(string $id, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est authentifié
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(
                ['message' => 'Authentification requise'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Vérifier les permissions (seuls les admins peuvent modifier)
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                ['message' => 'Accès refusé. Seuls les administrateurs peuvent modifier les tours.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Récupérer le tour
        $tour = $this->tourRepository->find($id);
        if (!$tour) {
            return new JsonResponse(
                ['message' => 'Tour non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer les données JSON
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['imageUrls']) || !is_array($data['imageUrls'])) {
            return new JsonResponse(
                [
                    'message' => 'Le champ "imageUrls" est requis et doit être un tableau',
                    'error' => 'Format invalide'
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Ajouter les URLs d'images au tour
        $addedUrls = [];
        foreach ($data['imageUrls'] as $imageUrl) {
            if (is_string($imageUrl) && !empty($imageUrl)) {
                $tour->addImageUrl($imageUrl);
                $addedUrls[] = $imageUrl;
            }
        }

        // Sauvegarder en base de données
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => count($addedUrls) . ' image(s) ajoutée(s) avec succès',
            'tour' => [
                'id' => $tour->getId(),
                'title' => $tour->getTitle(),
                'imageUrls' => $tour->getImageUrls(),
            ],
            'addedUrls' => $addedUrls,
        ], Response::HTTP_OK);
    }

    /**
     * Met à jour toutes les URLs d'images d'un tour
     */
    #[Route('/{id}/images', name: 'update_images', methods: ['PUT', 'PATCH'])]
    public function updateImages(string $id, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est authentifié
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(
                ['message' => 'Authentification requise'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Vérifier les permissions (seuls les admins peuvent modifier)
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                ['message' => 'Accès refusé. Seuls les administrateurs peuvent modifier les tours.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Récupérer le tour
        $tour = $this->tourRepository->find($id);
        if (!$tour) {
            return new JsonResponse(
                ['message' => 'Tour non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer les données JSON
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['imageUrls']) || !is_array($data['imageUrls'])) {
            return new JsonResponse(
                [
                    'message' => 'Le champ "imageUrls" est requis et doit être un tableau',
                    'error' => 'Format invalide'
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Filtrer les URLs valides
        $validUrls = [];
        foreach ($data['imageUrls'] as $imageUrl) {
            if (is_string($imageUrl) && !empty($imageUrl)) {
                $validUrls[] = $imageUrl;
            }
        }

        // Mettre à jour les URLs d'images
        $tour->setImageUrls($validUrls);

        // Sauvegarder en base de données
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Images mises à jour avec succès',
            'tour' => [
                'id' => $tour->getId(),
                'title' => $tour->getTitle(),
                'imageUrls' => $tour->getImageUrls(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Supprime une URL d'image d'un tour
     */
    #[Route('/{id}/images/{imageUrl}', name: 'remove_image', methods: ['DELETE'])]
    public function removeImage(string $id, string $imageUrl, Request $request): JsonResponse
    {
        // Décoder l'URL si elle est encodée
        $imageUrl = urldecode($imageUrl);

        // Vérifier que l'utilisateur est authentifié
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(
                ['message' => 'Authentification requise'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Vérifier les permissions (seuls les admins peuvent modifier)
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                ['message' => 'Accès refusé. Seuls les administrateurs peuvent modifier les tours.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Récupérer le tour
        $tour = $this->tourRepository->find($id);
        if (!$tour) {
            return new JsonResponse(
                ['message' => 'Tour non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Supprimer l'URL d'image
        $tour->removeImageUrl($imageUrl);

        // Sauvegarder en base de données
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Image supprimée avec succès',
            'tour' => [
                'id' => $tour->getId(),
                'title' => $tour->getTitle(),
                'imageUrls' => $tour->getImageUrls(),
            ],
        ], Response::HTTP_OK);
    }
}

