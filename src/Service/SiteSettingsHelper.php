<?php

namespace App\Service;

use App\Repository\SiteSettingRepository;

final class SiteSettingsHelper
{
    public function __construct(
        private SiteSettingRepository $repository
    ) {
    }

    public function isBookingEnabled(): bool
    {
        return $this->getBoolean('booking_enabled', true);
    }

    public function getMaxBookingParticipants(): int
    {
        return $this->getNumber('max_booking_participants', 20);
    }

    public function getCurrency(): string
    {
        $setting = $this->repository->findByKey('currency');

        return $setting?->getValue() ?? 'EUR';
    }

    public function getAdminNotificationEmail(): ?string
    {
        $setting = $this->repository->findByKey('admin_notification_email');

        return $setting?->getValue();
    }

    private function getBoolean(string $key, bool $default): bool
    {
        $setting = $this->repository->findByKey($key);
        if ($setting === null || $setting->getValue() === null) {
            return $default;
        }

        return filter_var($setting->getValue(), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function getNumber(string $key, int $default): int
    {
        $setting = $this->repository->findByKey($key);
        if ($setting === null || $setting->getValue() === null || !is_numeric($setting->getValue())) {
            return $default;
        }

        return (int) $setting->getValue();
    }
}
