<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\BrandedEmailRenderer;
use App\Service\EmailService;
use App\Service\MailLogger;
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
        private BrandedEmailRenderer $emailRenderer,
        private UserPasswordHasherInterface $passwordHasher,
        private MailLogger $mailLogger
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
                $message = $this->emailRenderer->passwordReset($plainToken);
                $this->emailService->sendEmail(
                    $user->getEmail(),
                    'Réinitialisation de mot de passe MadaBooking',
                    $message['html'],
                    true,
                    null,
                    null,
                    $message['text']
                );
                $this->mailLogger->info('E-mail envoyé.', ['type' => 'reinitialisation_mot_de_passe', 'to' => MailLogger::mask($user->getEmail())]);
            } catch (\Throwable $e) {
                // La réponse publique reste identique, mais l'échec est journalisé.
                $this->mailLogger->error('Échec d\'envoi d\'e-mail.', [
                    'type' => 'reinitialisation_mot_de_passe',
                    'to' => MailLogger::mask($user->getEmail()),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'at' => basename($e->getFile()) . ':' . $e->getLine(),
                ]);
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
