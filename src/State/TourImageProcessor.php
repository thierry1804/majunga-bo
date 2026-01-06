<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Processor qui s'assure que les imageUrls sont correctement initialisées
 */
final class TourImageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Tour) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        // Récupérer les imageUrls actuels (via réflexion pour voir la valeur réelle avant le getter)
        $reflection = new \ReflectionClass($data);
        $property = $reflection->getProperty('imageUrls');
        $property->setAccessible(true);
        $rawImageUrls = $property->getValue($data);
        
        // Log pour déboguer (à retirer en production)
        error_log('TourImageProcessor - Raw imageUrls: ' . json_encode($rawImageUrls));
        error_log('TourImageProcessor - Operation: ' . $operation->getName());
        
        // Si imageUrls est null (non initialisé), l'initialiser à un tableau vide
        if ($rawImageUrls === null) {
            $rawImageUrls = [];
        }
        
        // Filtrer les URLs vides ou invalides
        if (is_array($rawImageUrls)) {
            $validUrls = array_filter($rawImageUrls, function($url) {
                return is_string($url) && !empty(trim($url));
            });
            $validUrls = array_values($validUrls); // Réindexer le tableau
            
            // Forcer la mise à jour en créant un nouveau tableau (pour que Doctrine détecte le changement)
            $data->setImageUrls($validUrls);
            
            // Si l'entité est déjà gérée, forcer la détection du changement
            if ($this->entityManager->contains($data)) {
                // Créer un nouveau tableau pour forcer Doctrine à détecter le changement
                $this->entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
                    $this->entityManager->getClassMetadata(Tour::class),
                    $data
                );
            }
            
            error_log('TourImageProcessor - Valid URLs after filter: ' . json_encode($validUrls));
        } else {
            // Si ce n'est pas un tableau, initialiser à un tableau vide
            $data->setImageUrls([]);
            
            // Forcer la détection du changement si l'entité est gérée
            if ($this->entityManager->contains($data)) {
                $this->entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
                    $this->entityManager->getClassMetadata(Tour::class),
                    $data
                );
            }
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

