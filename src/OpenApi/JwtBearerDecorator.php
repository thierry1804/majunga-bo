<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model;
use Symfony\Component\HttpFoundation\Response;

final class JwtBearerDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?: new \ArrayObject();
        
        $securitySchemes['bearerAuth'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        $components = $components->withSecuritySchemes($securitySchemes);
        $openApi = $openApi->withComponents($components);

        // Ajouter les endpoints d'authentification manquants
        $paths = $openApi->getPaths();
        
        // POST /api/register (mise à jour avec le champ role optionnel)
        $paths->addPath('/api/register', (new Model\PathItem())->withPost(
            (new Model\Operation())
                ->withOperationId('registerUser')
                ->withTags(['User'])
                ->withSummary('Créer un nouvel utilisateur')
                ->withDescription('Crée un nouveau compte utilisateur. Le champ "role" est optionnel et permet de définir un rôle spécifique (ex: ROLE_ADMIN). Si non fourni, l\'utilisateur aura uniquement ROLE_USER.')
                ->withRequestBody(
                    (new Model\RequestBody())
                        ->withDescription('Données de l\'utilisateur à créer')
                        ->withRequired(true)
                        ->withContent(new \ArrayObject([
                            'application/json' => new \ArrayObject([
                                'schema' => new \ArrayObject([
                                    'type' => 'object',
                                    'required' => new \ArrayObject(['email', 'password']),
                                    'properties' => new \ArrayObject([
                                        'email' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'email',
                                            'description' => 'Adresse email de l\'utilisateur',
                                            'example' => 'user@example.com',
                                        ]),
                                        'password' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'password',
                                            'description' => 'Mot de passe de l\'utilisateur',
                                            'example' => 'password123',
                                        ]),
                                        'role' => new \ArrayObject([
                                            'type' => 'string',
                                            'description' => 'Rôle à attribuer à l\'utilisateur (optionnel, doit commencer par ROLE_)',
                                            'example' => 'ROLE_ADMIN',
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]))
                )
                ->withResponses([
                    Response::HTTP_CREATED => [
                        'description' => 'Utilisateur créé avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'User registered successfully',
                                        ],
                                        'token' => [
                                            'type' => 'string',
                                            'example' => 'eyJ0eXAiOiJKV1QiLCJhbGc...',
                                        ],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'integer',
                                                    'example' => 1,
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['ROLE_USER', 'ROLE_ADMIN'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_BAD_REQUEST => [
                        'description' => 'Erreur de validation',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Email and password are required',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_CONFLICT => [
                        'description' => 'Utilisateur déjà existant',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'User already exists',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
        ));
        
        // GET /api/me
        $paths->addPath('/api/me', (new Model\PathItem())->withGet(
            (new Model\Operation())
                ->withOperationId('getMe')
                ->withTags(['Authentication'])
                ->withSummary('Valide le token et retourne les informations de l\'utilisateur')
                ->withDescription('Vérifie la validité du token JWT et retourne les informations de l\'utilisateur connecté.')
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Token valide - Informations utilisateur',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'valid' => [
                                            'type' => 'boolean',
                                            'example' => true,
                                        ],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'integer',
                                                    'example' => 1,
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['ROLE_USER'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => [
                        'description' => 'Token invalide ou expiré',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Token invalide ou expiré',
                                        ],
                                        'valid' => [
                                            'type' => 'boolean',
                                            'example' => false,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        // POST /api/refresh
        $paths->addPath('/api/refresh', (new Model\PathItem())->withPost(
            (new Model\Operation())
                ->withOperationId('refreshToken')
                ->withTags(['Authentication'])
                ->withSummary('Renouvelle le token JWT')
                ->withDescription('Génère un nouveau token JWT sans nécessiter de se reconnecter. Le token actuel doit être valide.')
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Nouveau token généré avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'example' => 'eyJ0eXAiOiJKV1QiLCJhbGc...',
                                        ],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'integer',
                                                    'example' => 1,
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => [
                        'description' => 'Token invalide ou expiré',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Token invalide ou expiré',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        // POST /api/send-email
        $paths->addPath('/api/send-email', (new Model\PathItem())->withPost(
            (new Model\Operation())
                ->withOperationId('sendEmail')
                ->withTags(['Email'])
                ->withSummary('Envoie un email')
                ->withDescription('Envoie un email via SMTP. Le destinataire, le sujet et le corps du message sont obligatoires.')
                ->withRequestBody(
                    (new Model\RequestBody())
                        ->withDescription('Données de l\'email à envoyer')
                        ->withContent(new \ArrayObject([
                            'application/json' => new \ArrayObject([
                                'schema' => new \ArrayObject([
                                    'type' => 'object',
                                    'required' => new \ArrayObject(['to', 'subject', 'body']),
                                    'properties' => new \ArrayObject([
                                        'to' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'email',
                                            'description' => 'Adresse email du destinataire',
                                            'example' => 'destinataire@example.com',
                                        ]),
                                        'subject' => new \ArrayObject([
                                            'type' => 'string',
                                            'description' => 'Sujet de l\'email',
                                            'example' => 'Bienvenue sur notre plateforme',
                                        ]),
                                        'body' => new \ArrayObject([
                                            'type' => 'string',
                                            'description' => 'Corps du message (texte ou HTML)',
                                            'example' => 'Bonjour, ceci est un message de test.',
                                        ]),
                                        'isHtml' => new \ArrayObject([
                                            'type' => 'boolean',
                                            'description' => 'Indique si le corps est en HTML',
                                            'default' => false,
                                            'example' => false,
                                        ]),
                                        'cc' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'email',
                                            'description' => 'Adresse email en copie (optionnel)',
                                            'example' => 'cc@example.com',
                                        ]),
                                        'bcc' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'email',
                                            'description' => 'Adresse email en copie invisible (optionnel)',
                                            'example' => 'bcc@example.com',
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]))
                )
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Email envoyé avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Email envoyé avec succès',
                                        ],
                                        'to' => [
                                            'type' => 'string',
                                            'example' => 'destinataire@example.com',
                                        ],
                                        'subject' => [
                                            'type' => 'string',
                                            'example' => 'Bienvenue sur notre plateforme',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_BAD_REQUEST => [
                        'description' => 'Erreurs de validation',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Erreurs de validation',
                                        ],
                                        'errors' => [
                                            'type' => 'object',
                                            'example' => [
                                                'to' => 'Le champ "to" (destinataire) est obligatoire',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR => [
                        'description' => 'Erreur lors de l\'envoi de l\'email',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Erreur lors de l\'envoi de l\'email',
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Connection timeout',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        // POST /api/users/{id}/promote
        $paths->addPath('/api/users/{id}/promote', (new Model\PathItem())->withPost(
            (new Model\Operation())
                ->withOperationId('promoteUser')
                ->withTags(['User'])
                ->withSummary('Promouvoir un utilisateur à un rôle')
                ->withDescription('Promouvoit un utilisateur à un rôle spécifique (ex: ROLE_ADMIN). Nécessite le rôle ROLE_ADMIN.')
                ->withParameters([
                    new Model\Parameter(
                        'id',
                        'path',
                        'ID de l\'utilisateur à promouvoir',
                        required: true,
                        schema: ['type' => 'integer', 'example' => 2]
                    ),
                ])
                ->withRequestBody(
                    (new Model\RequestBody())
                        ->withDescription('Rôle à attribuer à l\'utilisateur')
                        ->withRequired(true)
                        ->withContent(new \ArrayObject([
                            'application/json' => new \ArrayObject([
                                'schema' => new \ArrayObject([
                                    'type' => 'object',
                                    'required' => new \ArrayObject(['role']),
                                    'properties' => new \ArrayObject([
                                        'role' => new \ArrayObject([
                                            'type' => 'string',
                                            'description' => 'Rôle à attribuer (doit commencer par ROLE_)',
                                            'example' => 'ROLE_ADMIN',
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]))
                )
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Utilisateur promu avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Utilisateur promu avec succès',
                                        ],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => [
                                                    'type' => 'integer',
                                                    'example' => 2,
                                                ],
                                                'email' => [
                                                    'type' => 'string',
                                                    'example' => 'user@example.com',
                                                ],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'string',
                                                    ],
                                                    'example' => ['ROLE_USER', 'ROLE_ADMIN'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_BAD_REQUEST => [
                        'description' => 'Erreur de validation',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Le champ "role" est requis',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_FORBIDDEN => [
                        'description' => 'Accès refusé - Seuls les administrateurs peuvent promouvoir des utilisateurs',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Accès refusé. Seuls les administrateurs peuvent promouvoir des utilisateurs.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_NOT_FOUND => [
                        'description' => 'Utilisateur non trouvé',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Utilisateur non trouvé',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        // POST /api/images/upload
        $paths->addPath('/api/images/upload', (new Model\PathItem())->withPost(
            (new Model\Operation())
                ->withOperationId('uploadImage')
                ->withTags(['Images'])
                ->withSummary('Uploader une image et la convertir en WebP')
                ->withDescription('Upload une image (JPEG, PNG, GIF, WebP ou BMP) et la convertit automatiquement en format WebP avant de la stocker dans le dossier images. Taille maximum : 10MB.')
                ->withRequestBody(
                    (new Model\RequestBody())
                        ->withDescription('Fichier image à uploader')
                        ->withRequired(true)
                        ->withContent(new \ArrayObject([
                            'multipart/form-data' => new \ArrayObject([
                                'schema' => new \ArrayObject([
                                    'type' => 'object',
                                    'required' => new \ArrayObject(['image']),
                                    'properties' => new \ArrayObject([
                                        'image' => new \ArrayObject([
                                            'type' => 'string',
                                            'format' => 'binary',
                                            'description' => 'Fichier image à uploader (JPEG, PNG, GIF, WebP ou BMP, max 10MB)',
                                        ]),
                                    ]),
                                ]),
                            ]),
                        ]))
                )
                ->withResponses([
                    Response::HTTP_CREATED => [
                        'description' => 'Image uploadée et convertie en WebP avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Image uploadée et convertie en WebP avec succès',
                                        ],
                                        'filename' => [
                                            'type' => 'string',
                                            'example' => 'mon-image-1234567890.webp',
                                        ],
                                        'url' => [
                                            'type' => 'string',
                                            'example' => '/images/mon-image-1234567890.webp',
                                        ],
                                        'size' => [
                                            'type' => 'integer',
                                            'example' => 245678,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_BAD_REQUEST => [
                        'description' => 'Erreur de validation',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Aucun fichier image fourni',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => [
                        'description' => 'Authentification requise',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Authentification requise',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR => [
                        'description' => 'Erreur lors de l\'upload ou de la conversion',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Erreur lors de l\'upload de l\'image',
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Erreur lors de la conversion de l\'image en WebP',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        // GET /api/images/{filename}
        $paths->addPath('/api/images/{filename}', (new Model\PathItem())->withGet(
            (new Model\Operation())
                ->withOperationId('getImage')
                ->withTags(['Images'])
                ->withSummary('Récupérer une image')
                ->withDescription('Récupère une image depuis le serveur. Cet endpoint est accessible publiquement et ne nécessite pas d\'authentification. Les images sont servies au format WebP.')
                ->withParameters([
                    new Model\Parameter(
                        'filename',
                        'path',
                        'Nom du fichier image à récupérer',
                        required: true,
                        schema: ['type' => 'string', 'example' => 'antsanitia-resort-695cb50c76721.webp']
                    ),
                ])
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Image récupérée avec succès',
                        'content' => [
                            'image/webp' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_NOT_FOUND => [
                        'description' => 'Image non trouvée',
                        'content' => [
                            'text/plain' => [
                                'schema' => [
                                    'type' => 'string',
                                    'example' => 'Image non trouvée',
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_FORBIDDEN => [
                        'description' => 'Accès non autorisé',
                        'content' => [
                            'text/plain' => [
                                'schema' => [
                                    'type' => 'string',
                                    'example' => 'Accès non autorisé',
                                ],
                            ],
                        ],
                    ],
                ])
        )->withDelete(
            (new Model\Operation())
                ->withOperationId('deleteImage')
                ->withTags(['Images'])
                ->withSummary('Supprimer une image')
                ->withDescription('Supprime une image du dossier images. Seuls les fichiers WebP peuvent être supprimés.')
                ->withParameters([
                    new Model\Parameter(
                        'filename',
                        'path',
                        'Nom du fichier image à supprimer',
                        required: true,
                        schema: ['type' => 'string', 'example' => 'mon-image-1234567890.webp']
                    ),
                ])
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'Image supprimée avec succès',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Image supprimée avec succès',
                                        ],
                                        'filename' => [
                                            'type' => 'string',
                                            'example' => 'mon-image-1234567890.webp',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_UNAUTHORIZED => [
                        'description' => 'Authentification requise',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Authentification requise',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_NOT_FOUND => [
                        'description' => 'Image non trouvée',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Image non trouvée',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_FORBIDDEN => [
                        'description' => 'Accès non autorisé',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Accès non autorisé',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR => [
                        'description' => 'Erreur lors de la suppression',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Erreur lors de la suppression de l\'image',
                                        ],
                                        'error' => [
                                            'type' => 'string',
                                            'example' => 'Permission denied',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
                ->withSecurity([['bearerAuth' => []]])
        ));

        return $openApi;
    }
}
