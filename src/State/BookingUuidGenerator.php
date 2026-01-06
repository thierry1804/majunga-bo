<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Booking;
use Symfony\Component\Uid\Uuid;

final class BookingUuidGenerator implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Booking || $data->getId() !== null) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        // Générer un UUID si l'ID n'est pas déjà défini
        $data->setId((string) Uuid::v4());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

