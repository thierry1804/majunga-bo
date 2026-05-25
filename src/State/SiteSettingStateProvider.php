<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class SiteSettingStateProvider implements ProviderInterface
{
    public function __construct(
        private SiteSettingRepository $repository,
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|object|null
    {
        $user = $this->security->getUser();
        $isAdmin = $user && $this->security->isGranted('ROLE_ADMIN');

        // Pour Get (opération sur un seul élément) - il y a un ID dans uriVariables
        if (isset($uriVariables['id'])) {
            $id = $uriVariables['id'];
            $setting = $this->repository->find($id);
            
            if (!$setting) {
                return null;
            }

            // Si l'utilisateur n'est pas admin, vérifier que le paramètre est public
            if (!$isAdmin && !$setting->isPublic()) {
                return null;
            }

            return $setting;
        }

        // Pour GetCollection - retourner tous les paramètres ou seulement les publics
        if ($isAdmin) {
            return $this->repository->findAll();
        }

        // Utilisateurs normaux : seulement les paramètres publics
        return $this->repository->findPublicSettings();
    }
}

