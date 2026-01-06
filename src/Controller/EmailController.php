<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class EmailController extends AbstractController
{
    public function __construct(
        private EmailService $emailService,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/send-email', name: 'send_email', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation des données
        $to = $data['to'] ?? null;
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;
        $isHtml = $data['isHtml'] ?? false;
        $cc = $data['cc'] ?? null;
        $bcc = $data['bcc'] ?? null;

        // Validation des champs obligatoires
        $errors = [];
        
        if (empty($to)) {
            $errors['to'] = 'Le champ "to" (destinataire) est obligatoire';
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors['to'] = 'L\'adresse email du destinataire n\'est pas valide';
        }

        if (empty($subject)) {
            $errors['subject'] = 'Le champ "subject" (sujet) est obligatoire';
        }

        if (empty($body)) {
            $errors['body'] = 'Le champ "body" (corps du message) est obligatoire';
        }

        if ($cc && !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $errors['cc'] = 'L\'adresse email en copie (cc) n\'est pas valide';
        }

        if ($bcc && !filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            $errors['bcc'] = 'L\'adresse email en copie invisible (bcc) n\'est pas valide';
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'message' => 'Erreurs de validation',
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->emailService->sendEmail(
                $to,
                $subject,
                $body,
                (bool) $isHtml,
                $cc,
                $bcc
            );

            return new JsonResponse([
                'message' => 'Email envoyé avec succès',
                'to' => $to,
                'subject' => $subject
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Erreur lors de l\'envoi de l\'email',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

