<?php

namespace App\Service;

use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Journal dédié aux e-mails (var/log/mail.log). Sans monolog, le logger par défaut de Symfony
 * écrit sur stderr, souvent perdu en production : on écrit donc dans un fichier explicite,
 * et on duplique dans le journal d'erreurs PHP. Ne lève jamais d'exception.
 */
final class MailLogger extends AbstractLogger
{
    public function __construct(
        #[Autowire('%kernel.logs_dir%')]
        private string $logDir
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $line = sprintf(
            '[%s] mail.%s: %s %s',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper((string) $level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        ) . PHP_EOL;

        try {
            if (!is_dir($this->logDir)) {
                @mkdir($this->logDir, 0775, true);
            }
            if (@file_put_contents($this->logDir . '/mail.log', $line, FILE_APPEND | LOCK_EX) === false) {
                error_log(trim($line));
            } elseif (in_array($level, ['error', 'critical', 'alert', 'emergency'], true)) {
                error_log(trim($line));
            }
        } catch (\Throwable) {
            // Un journal défaillant ne doit jamais casser une réservation.
        }
    }

    /** Masque la partie locale d'une adresse : jean.dupont@gmail.com devient j***@gmail.com. */
    public static function mask(?string $email): string
    {
        $email = (string) $email;
        $at = strrpos($email, '@');
        if ($at === false) {
            return $email === '' ? '(vide)' : '(adresse sans @)';
        }

        return substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
