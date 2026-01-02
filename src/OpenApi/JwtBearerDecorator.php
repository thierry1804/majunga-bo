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

        return $openApi;
    }
}
