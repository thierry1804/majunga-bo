<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/images', name: 'api_images_')]
class ImageController extends AbstractController
{
    private string $imagesDirectory;

    public function __construct(
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager
    ) {
        $this->imagesDirectory = $this->projectDir . '/public/images';
        
        // Créer le dossier images s'il n'existe pas
        if (!is_dir($this->imagesDirectory)) {
            mkdir($this->imagesDirectory, 0755, true);
        }
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        // Debug: Vérifier les headers reçus
        $authHeader = $request->headers->get('Authorization');
        
        // Vérifier que l'utilisateur est authentifié
        // Le firewall JWT devrait déjà avoir vérifié, mais on double-vérifie pour un message d'erreur plus clair
        $user = $this->getUser();
        if (!$user) {
            // Si pas de header Authorization, donner un message plus clair
            if (!$authHeader) {
                return new JsonResponse(
                    [
                        'message' => 'Authentification requise',
                        'error' => 'Header Authorization manquant. Assurez-vous d\'envoyer: Authorization: Bearer <token>',
                        'debug' => [
                            'headers_received' => $request->headers->all(),
                            'content_type' => $request->headers->get('Content-Type')
                        ]
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }
            
            return new JsonResponse(
                [
                    'message' => 'Authentification requise',
                    'error' => 'Token JWT invalide ou expiré. Vérifiez que votre token est valide.',
                    'debug' => [
                        'auth_header_present' => !empty($authHeader),
                        'auth_header_preview' => $authHeader ? substr($authHeader, 0, 20) . '...' : null
                    ]
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $file = $request->files->get('image');

        if (!$file) {
            return new JsonResponse(
                ['message' => 'Aucun fichier image fourni'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifier que c'est bien une image
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return new JsonResponse(
                ['message' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP ou BMP)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifier la taille du fichier (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            return new JsonResponse(
                ['message' => 'Le fichier est trop volumineux (maximum 10MB)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // S'assurer que le dossier de destination existe
            $this->ensureDirectoryExists();

            // Générer un nom de fichier unique
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.webp';

            $webpPath = $this->imagesDirectory . '/' . $newFilename;
            $isWebP = $mimeType === 'image/webp';

            // Si l'image est déjà en WebP, simplement la copier
            if ($isWebP) {
                if (!copy($file->getPathname(), $webpPath)) {
                    return new JsonResponse(
                        ['message' => 'Erreur lors de la copie de l\'image'],
                        Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                }
                $message = 'Image WebP uploadée avec succès';
            } else {
                // Sinon, convertir l'image en WebP
                $conversionResult = $this->convertToWebP($file->getPathname(), $webpPath);

                if ($conversionResult['success'] === false) {
                    return new JsonResponse(
                        [
                            'message' => 'Erreur lors de la conversion de l\'image en WebP',
                            'error' => $conversionResult['error'] ?? 'Erreur inconnue',
                            'details' => $conversionResult['details'] ?? null
                        ],
                        Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                }
                $message = 'Image uploadée et convertie en WebP avec succès';
            }

            // Retourner l'URL de l'image
            $imageUrl = '/images/' . $newFilename;

            return new JsonResponse([
                'message' => $message,
                'filename' => $newFilename,
                'url' => $imageUrl,
                'size' => filesize($webpPath),
            ], Response::HTTP_CREATED);

        } catch (FileException $e) {
            return new JsonResponse(
                ['message' => 'Erreur lors de l\'upload de l\'image', 'error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{filename}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $filename, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est authentifié
        // Comme le firewall 'images' désactive la sécurité, on doit valider manuellement le token JWT
        $user = $this->getUser();
        
        // Si getUser() retourne null (à cause du firewall images), essayer de valider le token manuellement
        if (!$user) {
            $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
            $token = $extractor->extract($request);
            
            if (!$token) {
                return new JsonResponse(
                    [
                        'message' => 'Authentification requise',
                        'error' => 'Token JWT manquant. Assurez-vous d\'envoyer le header Authorization: Bearer <token>'
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }
            
            try {
                // Décoder le token JWT
                $payload = $this->jwtManager->decode($token);
                
                if (!$payload || !isset($payload['username'])) {
                    return new JsonResponse(
                        [
                            'message' => 'Authentification requise',
                            'error' => 'Token JWT invalide'
                        ],
                        Response::HTTP_UNAUTHORIZED
                    );
                }
                
                // Récupérer l'utilisateur depuis la base de données
                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $payload['username']]);
                
                if (!$user) {
                    return new JsonResponse(
                        [
                            'message' => 'Authentification requise',
                            'error' => 'Utilisateur non trouvé'
                        ],
                        Response::HTTP_UNAUTHORIZED
                    );
                }
            } catch (\Exception $e) {
                return new JsonResponse(
                    [
                        'message' => 'Authentification requise',
                        'error' => 'Token JWT invalide ou expiré: ' . $e->getMessage()
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }
        
        if (!$user instanceof User) {
            return new JsonResponse(
                [
                    'message' => 'Authentification requise',
                    'error' => 'Token JWT invalide ou expiré'
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Sécuriser le nom de fichier pour éviter les accès non autorisés
        $filename = basename($filename);
        $filePath = $this->imagesDirectory . '/' . $filename;

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            return new JsonResponse(
                ['message' => 'Image non trouvée'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Vérifier que c'est bien un fichier WebP dans le dossier images
        if (!str_ends_with($filename, '.webp') || !str_starts_with(realpath($filePath), realpath($this->imagesDirectory))) {
            return new JsonResponse(
                ['message' => 'Accès non autorisé'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            if (unlink($filePath)) {
                return new JsonResponse([
                    'message' => 'Image supprimée avec succès',
                    'filename' => $filename
                ], Response::HTTP_OK);
            } else {
                return new JsonResponse(
                    ['message' => 'Erreur lors de la suppression de l\'image'],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        } catch (\Exception $e) {
            return new JsonResponse(
                ['message' => 'Erreur lors de la suppression de l\'image', 'error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * S'assure que le dossier de destination existe, sinon le crée
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->imagesDirectory)) {
            if (!mkdir($this->imagesDirectory, 0755, true) && !is_dir($this->imagesDirectory)) {
                throw new \RuntimeException(sprintf('Impossible de créer le dossier de destination "%s"', $this->imagesDirectory));
            }
        }
    }

    /**
     * Convertit une image en WebP
     * 
     * @param string $sourcePath Chemin vers l'image source
     * @param string $destinationPath Chemin de destination pour le fichier WebP
     * @return array ['success' => bool, 'error' => string|null, 'details' => array|null]
     */
    private function convertToWebP(string $sourcePath, string $destinationPath): array
    {
        // Vérifier si Imagick est disponible
        if (extension_loaded('imagick')) {
            return $this->convertWithImagick($sourcePath, $destinationPath);
        }

        // Sinon, utiliser GD
        if (extension_loaded('gd')) {
            if (!function_exists('imagewebp')) {
                return [
                    'success' => false,
                    'error' => 'Extension GD disponible mais fonction imagewebp() non supportée',
                    'details' => [
                        'gd_loaded' => true,
                        'imagewebp_available' => false,
                        'gd_info' => function_exists('gd_info') ? gd_info() : null
                    ]
                ];
            }
            return $this->convertWithGD($sourcePath, $destinationPath);
        }

        return [
            'success' => false,
            'error' => 'Aucune extension de traitement d\'image disponible (Imagick ou GD requis)',
            'details' => [
                'imagick_loaded' => extension_loaded('imagick'),
                'gd_loaded' => extension_loaded('gd'),
                'gd_webp_support' => extension_loaded('gd') && function_exists('imagewebp')
            ]
        ];
    }

    /**
     * Convertit une image en WebP avec Imagick
     */
    private function convertWithImagick(string $sourcePath, string $destinationPath): array
    {
        try {
            // Vérifier que le fichier source existe
            if (!file_exists($sourcePath)) {
                return [
                    'success' => false,
                    'error' => 'Le fichier source n\'existe pas',
                    'details' => ['source_path' => $sourcePath]
                ];
            }

            // Vérifier que le dossier de destination est accessible en écriture
            $destinationDir = dirname($destinationPath);
            if (!is_writable($destinationDir)) {
                return [
                    'success' => false,
                    'error' => 'Le dossier de destination n\'est pas accessible en écriture',
                    'details' => [
                        'destination_dir' => $destinationDir,
                        'is_writable' => is_writable($destinationDir),
                        'permissions' => file_exists($destinationDir) ? substr(sprintf('%o', fileperms($destinationDir)), -4) : null
                    ]
                ];
            }

            $image = new \Imagick($sourcePath);
            
            // Vérifier que le format WebP est supporté
            $formats = $image->queryFormats('WEBP');
            if (empty($formats)) {
                $image->destroy();
                return [
                    'success' => false,
                    'error' => 'Le format WebP n\'est pas supporté par Imagick',
                    'details' => ['available_formats' => $image->queryFormats()]
                ];
            }
            
            // Définir le format WebP
            $image->setImageFormat('webp');
            
            // Optimiser la qualité (0-100, 80 est un bon compromis)
            $image->setImageCompressionQuality(80);
            
            // Écrire le fichier
            $result = $image->writeImage($destinationPath);
            $image->destroy();
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => 'Échec de l\'écriture du fichier WebP',
                    'details' => ['destination_path' => $destinationPath]
                ];
            }

            // Vérifier que le fichier a bien été créé
            if (!file_exists($destinationPath)) {
                return [
                    'success' => false,
                    'error' => 'Le fichier WebP n\'a pas été créé après la conversion',
                    'details' => ['destination_path' => $destinationPath]
                ];
            }
            
            return ['success' => true];
        } catch (\ImagickException $e) {
            return [
                'success' => false,
                'error' => 'Erreur Imagick: ' . $e->getMessage(),
                'details' => [
                    'exception_type' => get_class($e),
                    'code' => $e->getCode()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la conversion: ' . $e->getMessage(),
                'details' => [
                    'exception_type' => get_class($e),
                    'code' => $e->getCode()
                ]
            ];
        }
    }

    /**
     * Convertit une image en WebP avec GD
     */
    private function convertWithGD(string $sourcePath, string $destinationPath): array
    {
        try {
            // Vérifier que le fichier source existe
            if (!file_exists($sourcePath)) {
                return [
                    'success' => false,
                    'error' => 'Le fichier source n\'existe pas',
                    'details' => ['source_path' => $sourcePath]
                ];
            }

            // Vérifier que le dossier de destination est accessible en écriture
            $destinationDir = dirname($destinationPath);
            if (!is_writable($destinationDir)) {
                return [
                    'success' => false,
                    'error' => 'Le dossier de destination n\'est pas accessible en écriture',
                    'details' => [
                        'destination_dir' => $destinationDir,
                        'is_writable' => is_writable($destinationDir),
                        'permissions' => file_exists($destinationDir) ? substr(sprintf('%o', fileperms($destinationDir)), -4) : null
                    ]
                ];
            }

            $mimeType = mime_content_type($sourcePath);
            
            // Charger l'image selon son type
            $image = match ($mimeType) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
                'image/png' => imagecreatefrompng($sourcePath),
                'image/gif' => imagecreatefromgif($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                'image/bmp' => function_exists('imagecreatefrombmp') ? imagecreatefrombmp($sourcePath) : null,
                default => null,
            };

            if (!$image) {
                $lastError = error_get_last();
                return [
                    'success' => false,
                    'error' => 'Impossible de charger l\'image depuis le fichier source',
                    'details' => [
                        'mime_type' => $mimeType,
                        'source_path' => $sourcePath,
                        'php_error' => $lastError ? $lastError['message'] : null,
                        'gd_functions_available' => [
                            'imagecreatefromjpeg' => function_exists('imagecreatefromjpeg'),
                            'imagecreatefrompng' => function_exists('imagecreatefrompng'),
                            'imagecreatefromgif' => function_exists('imagecreatefromgif'),
                            'imagecreatefromwebp' => function_exists('imagecreatefromwebp'),
                            'imagecreatefrombmp' => function_exists('imagecreatefrombmp'),
                        ]
                    ]
                ];
            }

            // Vérifier le support WebP dans GD
            if (!function_exists('imagewebp')) {
                imagedestroy($image);
                return [
                    'success' => false,
                    'error' => 'La fonction imagewebp() n\'est pas disponible dans GD',
                    'details' => ['gd_info' => function_exists('gd_info') ? gd_info() : null]
                ];
            }

            // Convertir en WebP avec une qualité de 80
            $result = imagewebp($image, $destinationPath, 80);
            
            // Libérer la mémoire
            imagedestroy($image);
            
            if (!$result) {
                $lastError = error_get_last();
                return [
                    'success' => false,
                    'error' => 'Échec de l\'écriture du fichier WebP',
                    'details' => [
                        'destination_path' => $destinationPath,
                        'php_error' => $lastError ? $lastError['message'] : null
                    ]
                ];
            }

            // Vérifier que le fichier a bien été créé
            if (!file_exists($destinationPath)) {
                return [
                    'success' => false,
                    'error' => 'Le fichier WebP n\'a pas été créé après la conversion',
                    'details' => ['destination_path' => $destinationPath]
                ];
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la conversion: ' . $e->getMessage(),
                'details' => [
                    'exception_type' => get_class($e),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
}

