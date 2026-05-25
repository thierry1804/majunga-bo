<?php

namespace App\Controller\Public;

use App\Entity\Booking;
use App\Entity\ShuttleSchedule;
use App\Entity\Tour;

final class PublicApiSerializer
{
    public function tourToArray(Tour $tour): array
    {
        return [
            'id' => $tour->getId(),
            'title' => $tour->getTitle(),
            'description' => $tour->getDescription(),
            'price' => $tour->getPrice(),
            'duration' => $tour->getDuration(),
            'highlights' => $tour->getHighlights(),
            'imageUrls' => $tour->getImageUrls(),
            'isActive' => $tour->isActive(),
            'createdAt' => $tour->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $tour->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function shuttleScheduleToArray(ShuttleSchedule $schedule): array
    {
        return [
            'id' => $schedule->getId(),
            'departureTime' => $schedule->getDepartureTime(),
            'arrivalTime' => $schedule->getArrivalTime(),
            'route' => $schedule->getRoute(),
            'from' => $schedule->getFrom(),
            'to' => $schedule->getTo(),
            'price' => $schedule->getPrice(),
            'direction' => $schedule->getDirection(),
            'availableSeats' => $schedule->getAvailableSeats(),
            'isActive' => $schedule->isActive(),
            'createdAt' => $schedule->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $schedule->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function bookingToArray(Booking $booking): array
    {
        return [
            'id' => $booking->getId(),
            'serviceType' => $booking->getServiceType(),
            'tour' => $booking->getTour() ? '/api/tours/' . $booking->getTour()->getId() : null,
            'shuttleSchedule' => $booking->getShuttleSchedule()
                ? '/api/shuttle_schedules/' . $booking->getShuttleSchedule()->getId()
                : null,
            'userEmail' => $booking->getUserEmail(),
            'userName' => $booking->getUserName(),
            'phone' => $booking->getPhone(),
            'specialRequests' => $booking->getSpecialRequests(),
            'bookingDate' => $booking->getBookingDate()?->format('Y-m-d'),
            'participants' => $booking->getParticipants(),
            'totalPrice' => $booking->getTotalPrice(),
            'status' => $booking->getStatus(),
            'paymentId' => $booking->getPaymentId(),
            'createdAt' => $booking->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $booking->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
