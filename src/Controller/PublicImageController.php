<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/images', name: 'api_public_images_')]
class PublicImageController extends AbstractController
{
    private string $imagesDirectory;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->imagesDirectory = $this->projectDir . '/public/images';
    }

    #[Route('/{filename}', name: 'get', methods: ['GET'])]
    public function get(string $filename): Response
    {
        // Sécuriser le nom de fichier pour éviter les accès non autorisés
        $filename = basename($filename);
        $filePath = $this->imagesDirectory . '/' . $filename;

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            return new Response('Image non trouvée', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que c'est bien un fichier WebP dans le dossier images
        if (!str_ends_with($filename, '.webp') || !str_starts_with(realpath($filePath), realpath($this->imagesDirectory))) {
            return new Response('Accès non autorisé', Response::HTTP_FORBIDDEN);
        }

        // Créer une réponse avec le fichier
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        $response->headers->set('Content-Type', 'image/webp');
        
        // Ajouter des headers pour le cache
        $response->setPublic();
        $response->setMaxAge(3600); // Cache pendant 1 heure
        $response->setSharedMaxAge(3600);

        return $response;
    }
}

