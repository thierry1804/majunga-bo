<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api', name: 'api_password_')]
class PasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/public/password/request-reset', name: 'request_reset', methods: ['POST'])]
    public function requestReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            return new JsonResponse(['message' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Réponse identique que l'utilisateur existe ou non (sécurité)
        if ($user instanceof User) {
            $plainToken = bin2hex(random_bytes(32));
            $resetToken = new PasswordResetToken();
            $resetToken->setId((string) Uuid::v4());
            $resetToken->setUser($user);
            $resetToken->setTokenHash(hash('sha256', $plainToken));
            $resetToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));
            $resetToken->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($resetToken);
            $this->entityManager->flush();

            try {
                $this->emailService->sendEmail(
                    $user->getEmail(),
                    'Réinitialisation de mot de passe — MadaBooking',
                    sprintf(
                        "Bonjour,\n\nUtilisez ce token pour réinitialiser votre mot de passe :\n%s\n\nCe token expire dans 1 heure.\n",
                        $plainToken
                    )
                );
            } catch (\Throwable) {
                // Ignorer les erreurs email côté réponse publique
            }
        }

        return new JsonResponse([
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.',
        ], Response::HTTP_OK);
    }

    #[Route('/public/password/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($token === '' || $password === '') {
            return new JsonResponse(['message' => 'token et password sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $this->tokenRepository->findValidByHash(hash('sha256', $token));
        if (!$resetToken) {
            return new JsonResponse(['message' => 'Token invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }

        $user = $resetToken->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $resetToken->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Mot de passe mis à jour avec succès'], Response::HTTP_OK);
    }

    #[Route('/password/change', name: 'change', methods: ['POST'])]
    public function change(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Authentification requise'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            return new JsonResponse(['message' => 'currentPassword et newPassword sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['message' => 'Mot de passe actuel incorrect'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Mot de passe modifié avec succès'], Response::HTTP_OK);
    }
}
