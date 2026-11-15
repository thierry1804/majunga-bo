<?php

namespace App\Service;

use App\Entity\Booking;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Rend les e-mails de MadaBooking dans le gabarit de la marque (version HTML et version texte).
 */
final class BrandedEmailRenderer
{
    private const STATUS_LABELS = [
        'pending' => 'En attente de paiement',
        'confirmed' => 'Confirmée',
        'cancelled' => 'Annulée',
    ];

    public function __construct(
        private Environment $twig,
        private SiteSettingsHelper $siteSettings,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl
    ) {
    }

    /** E-mail envoyé au client après sa réservation. @return array{html: string, text: string} */
    public function bookingConfirmation(Booking $booking): array
    {
        return $this->render('booking_confirmation', ['b' => $this->bookingView($booking)]);
    }

    /** E-mail envoyé à l'équipe pour chaque nouvelle réservation. @return array{html: string, text: string} */
    public function bookingAdminNotification(Booking $booking): array
    {
        return $this->render('booking_admin', ['b' => $this->bookingView($booking)]);
    }

    /** E-mail de réinitialisation du mot de passe, avec un lien vers le site. @return array{html: string, text: string} */
    public function passwordReset(string $plainToken): array
    {
        return $this->render('password_reset', [
            'resetUrl' => $this->siteUrl() . '/reset-password?token=' . rawurlencode($plainToken),
        ]);
    }

    /** @return array{html: string, text: string} */
    private function render(string $name, array $context): array
    {
        $context['brand'] = $this->brand();

        return [
            'html' => $this->twig->render(sprintf('email/%s.html.twig', $name), $context),
            'text' => $this->twig->render(sprintf('email/%s.txt.twig', $name), $context),
        ];
    }

    private function siteUrl(): string
    {
        return rtrim($this->frontendUrl, '/');
    }

    /** @return array<string, string> */
    private function brand(): array
    {
        return [
            'siteUrl' => $this->siteUrl(),
            'adminUrl' => $this->siteUrl() . '/admin/bookings',
            'whatsappUrl' => 'https://wa.me/261326478291',
            'phone' => '+261 32 64 782 91',
            'phoneLink' => '+261326478291',
            'email' => 'info@madabooking.mg',
            'address' => 'Boulevard Joffre, Mahajanga 401, Madagascar',
        ];
    }

    /** @return array<string, mixed> */
    private function bookingView(Booking $booking): array
    {
        $type = $booking->getServiceType();
        $status = $booking->getStatus() ?? 'pending';
        $requests = trim((string) $booking->getSpecialRequests());
        $routeLines = null;

        if ($type === 'custom_shuttle') {
            $label = 'Navette sur mesure';
            [$routeLines, $requests] = $this->parseCustomRoute($requests);
        } elseif ($type === 'shuttle') {
            $schedule = $booking->getShuttleSchedule();
            $trip = $schedule && $schedule->getFrom() && $schedule->getTo()
                ? sprintf('%s → %s', $schedule->getFrom(), $schedule->getTo())
                : (string) $schedule?->getRoute();
            $label = $schedule ? sprintf('Navette : %s, départ à %s', $trip, $schedule->getDepartureTime()) : 'Navette aéroport';
        } else {
            $label = (string) ($booking->getTour()?->getTitle() ?? 'Circuit');
        }

        return [
            'name' => $booking->getUserName(),
            'email' => $booking->getUserEmail(),
            'phone' => $booking->getPhone(),
            'reference' => $booking->getId(),
            'serviceLabel' => $label,
            'date' => $booking->getBookingDate()?->format('d/m/Y') ?? '',
            'participants' => $booking->getParticipants(),
            'amount' => sprintf('%s %s', number_format((float) $booking->getTotalPrice(), 2, ',', ' '), $this->siteSettings->getCurrency()),
            'status' => $status,
            'statusLabel' => self::STATUS_LABELS[$status] ?? $status,
            'isPending' => $status === 'pending',
            'isCustomShuttle' => $type === 'custom_shuttle',
            'routeLines' => $routeLines,
            'specialRequests' => $requests !== '' ? $requests : null,
        ];
    }

    /**
     * Le trajet d'une navette sur mesure est enregistré dans les demandes spéciales, une ligne par information.
     * On en tire les lignes à afficher (sans les coordonnées GPS) et le texte libre du client.
     *
     * @return array{0: array<int, array{label: string, value: string}>, 1: string}
     */
    private function parseCustomRoute(string $text): array
    {
        $labels = ['Départ' => 'Départ', 'Arrivée' => 'Arrivée', 'Heure souhaitée' => 'Heure souhaitée'];
        $lines = [];
        $free = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[Navette sur mesure]' || str_starts_with($line, 'Distance')) {
                continue;
            }
            if (preg_match('/^(Départ|Arrivée|Heure souhaitée)\s*:\s*(.+)$/u', $line, $m)) {
                $value = preg_replace('/\s*\(-?\d+\.\d+,\s*-?\d+\.\d+\)\s*$/', '', $m[2]);
                $lines[] = ['label' => $labels[$m[1]], 'value' => (string) $value];
            } elseif (preg_match('/^Demandes\s*:\s*(.+)$/u', $line, $m)) {
                $free[] = $m[1];
            } else {
                $free[] = $line;
            }
        }

        return [$lines ?: null, implode("\n", $free)];
    }
}
