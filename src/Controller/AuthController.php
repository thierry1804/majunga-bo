<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Cette route est gérée par le firewall json_login
        // Elle ne devrait jamais être appelée directement
        return new JsonResponse(['message' => 'Login endpoint'], Response::HTTP_OK);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(
                ['message' => 'Email and password are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return new JsonResponse(
                ['message' => 'User already exists'],
                Response::HTTP_CONFLICT
            );
        }

        // Créer un nouvel utilisateur
        $user = new User();
        $user->setEmail($data['email']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Gérer le rôle optionnel
        if (isset($data['role'])) {
            $role = $data['role'];
            
            // Valider le format du rôle
            if (!is_string($role) || !str_starts_with($role, 'ROLE_')) {
                return new JsonResponse(
                    ['message' => 'Le rôle doit commencer par "ROLE_" (ex: ROLE_ADMIN, ROLE_USER)'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            
            // Définir le rôle (ROLE_USER sera ajouté automatiquement par getRoles())
            $user->setRoles([$role]);
        }

        // Valider l'entité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(
                ['message' => 'Validation failed', 'errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Générer le token JWT
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Token invalide ou expiré', 'valid' => false],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'valid' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Token invalide ou expiré'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Générer un nouveau token
        $newToken = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $newToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/users/{id}/promote', name: 'promote_user', methods: ['POST'])]
    public function promoteUser(int $id, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur actuel est admin
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return new JsonResponse(
                ['message' => 'Accès refusé. Seuls les administrateurs peuvent promouvoir des utilisateurs.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Récupérer les données de la requête
        $data = json_decode($request->getContent(), true);

        if (!isset($data['role'])) {
            return new JsonResponse(
                ['message' => 'Le champ "role" est requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $role = $data['role'];
        
        // Valider le format du rôle
        if (!is_string($role) || !str_starts_with($role, 'ROLE_')) {
            return new JsonResponse(
                ['message' => 'Le rôle doit commencer par "ROLE_" (ex: ROLE_ADMIN, ROLE_USER)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupérer l'utilisateur à promouvoir
        $targetUser = $this->entityManager->getRepository(User::class)->find($id);

        if (!$targetUser) {
            return new JsonResponse(
                ['message' => 'Utilisateur non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Empêcher de se promouvoir soi-même (sécurité supplémentaire)
        if ($targetUser->getId() === $currentUser->getId()) {
            return new JsonResponse(
                ['message' => 'Vous ne pouvez pas modifier vos propres rôles'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupérer les rôles actuels et ajouter le nouveau rôle
        $currentRoles = $targetUser->getRoles();
        
        // Si le rôle n'est pas déjà présent, l'ajouter
        if (!in_array($role, $currentRoles, true)) {
            // Retirer ROLE_USER du tableau car il est toujours ajouté automatiquement
            $rolesToSet = array_filter($currentRoles, fn($r) => $r !== 'ROLE_USER');
            $rolesToSet[] = $role;
            $targetUser->setRoles($rolesToSet);
            
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'message' => 'Utilisateur promu avec succès',
            'user' => [
                'id' => $targetUser->getId(),
                'email' => $targetUser->getEmail(),
                'roles' => $targetUser->getRoles(),
            ],
        ], Response::HTTP_OK);
    }
}

