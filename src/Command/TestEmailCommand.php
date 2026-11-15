<?php

namespace App\Command;

use App\Entity\Booking;
use App\Entity\ShuttleSchedule;
use App\Service\BrandedEmailRenderer;
use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-email',
    description: 'Rend et envoie un e-mail de test de chaque type (confirmation, équipe, réinitialisation) avec des données factices',
)]
final class TestEmailCommand extends Command
{
    public function __construct(
        private BrandedEmailRenderer $renderer,
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('address', InputArgument::REQUIRED, 'Adresse qui recevra les trois e-mails de test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $address = (string) $input->getArgument('address');
        $booking = $this->fakeBooking($address);

        $io->title('Test d\'envoi des e-mails MadaBooking');
        $io->text(sprintf('Destinataire : %s', $address));
        $io->text(sprintf('MAILER_DSN (masqué) : %s', $this->maskedDsn((string) ($_SERVER['MAILER_DSN'] ?? $_ENV['MAILER_DSN'] ?? ''))));
        $io->newLine();

        $emails = [
            'Confirmation de réservation (client)' => [
                'Test : confirmation de réservation Navette',
                fn () => $this->renderer->bookingConfirmation($booking),
            ],
            'Notification de réservation (équipe)' => [
                'Test : nouvelle réservation Navette, Client Test',
                fn () => $this->renderer->bookingAdminNotification($booking),
            ],
            'Réinitialisation de mot de passe' => [
                'Test : réinitialisation de mot de passe MadaBooking',
                fn () => $this->renderer->passwordReset(bin2hex(random_bytes(16))),
            ],
        ];

        $failures = 0;
        foreach ($emails as $label => [$subject, $render]) {
            try {
                $message = $render();
                $io->text(sprintf('%s : rendu OK (HTML %d octets, texte %d octets)', $label, strlen($message['html']), strlen($message['text'])));
            } catch (\Throwable $e) {
                ++$failures;
                $this->printError($io, $label . ' : ÉCHEC DU RENDU', $e);
                continue;
            }

            try {
                $this->emailService->sendEmail($address, $subject, $message['html'], true, null, null, $message['text']);
                $io->success(sprintf('%s : envoyé à %s', $label, $address));
            } catch (\Throwable $e) {
                ++$failures;
                $this->printError($io, $label . ' : ÉCHEC DE L\'ENVOI', $e);
            }
        }

        if ($failures > 0) {
            $io->error(sprintf('%d e-mail(s) en échec sur %d. Voir les détails ci-dessus.', $failures, count($emails)));

            return Command::FAILURE;
        }

        $io->success('Les 3 e-mails ont été acceptés par le serveur SMTP. Vérifiez la boîte de réception ET les courriers indésirables.');

        return Command::SUCCESS;
    }

    private function fakeBooking(string $address): Booking
    {
        $schedule = (new ShuttleSchedule())
            ->setId('00000000-0000-4000-8000-000000000000')
            ->setDepartureTime('08:30')
            ->setArrivalTime('10:00')
            ->setRoute('Aéroport → Centre-ville')
            ->setFrom('Aéroport')
            ->setTo('Centre-ville');

        return (new Booking())
            ->setId('00000000-0000-4000-8000-00000000test')
            ->setServiceType('shuttle')
            ->setShuttleSchedule($schedule)
            ->setUserEmail($address)
            ->setUserName('Client Test')
            ->setPhone('+261 32 00 000 00')
            ->setBookingDate(new \DateTimeImmutable('+7 days'))
            ->setParticipants(2)
            ->setTotalPrice('40.00')
            ->setStatus('pending')
            ->setSpecialRequests('Message de test : aucune action requise.');
    }

    private function printError(SymfonyStyle $io, string $title, \Throwable $e): void
    {
        $io->error($title);
        $io->text(sprintf('%s : %s', $e::class, $e->getMessage()));
        $io->text(sprintf('Fichier : %s:%d', $e->getFile(), $e->getLine()));
        for ($previous = $e->getPrevious(); $previous; $previous = $previous->getPrevious()) {
            $io->text(sprintf('Causé par %s : %s', $previous::class, $previous->getMessage()));
        }
        $io->writeln($e->getTraceAsString(), OutputInterface::VERBOSITY_VERBOSE);
    }

    private function maskedDsn(string $dsn): string
    {
        return $dsn === '' ? '(non défini)' : preg_replace('#//([^:@/]*):[^@]*@#', '//$1:***@', $dsn);
    }
}
