<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\InvalidArgumentException;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private ?string $fromName = null
    ) {
    }

    /**
     * Envoie un email
     *
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $body Corps du message (HTML ou texte)
     * @param bool $isHtml Indique si le corps est en HTML
     * @param string|null $cc Copie carbone
     * @param string|null $bcc Copie carbone invisible
     * @return void
     * @throws InvalidArgumentException
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $body,
        bool $isHtml = false,
        ?string $cc = null,
        ?string $bcc = null
    ): void {
        $email = (new Email())
            ->from($this->fromName ? sprintf('%s <%s>', $this->fromName, $this->fromEmail) : $this->fromEmail)
            ->to($to)
            ->subject($subject);

        if ($isHtml) {
            $email->html($body);
        } else {
            $email->text($body);
        }

        if ($cc) {
            $email->cc($cc);
        }

        if ($bcc) {
            $email->bcc($bcc);
        }

        $this->mailer->send($email);
    }
}

