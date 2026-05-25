<?php

namespace App\Controller;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_public_site_settings_')]
class PublicSiteSettingController extends AbstractController
{
    public function __construct(
        private SiteSettingRepository $repository
    ) {
    }

    /**
     * Convertir un SiteSetting en tableau pour la réponse JSON
     */
    private function settingToArray(SiteSetting $setting): array
    {
        $value = $setting->getValue();
        $valueType = $setting->getValueType();

        // Convertir la valeur selon son type
        $convertedValue = $value;
        if ($value !== null) {
            switch ($valueType) {
                case 'boolean':
                    $convertedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                    break;
                case 'number':
                    if (is_numeric($value)) {
                        $convertedValue = str_contains($value, '.') ? (float) $value : (int) $value;
                    }
                    break;
                case 'json':
                    $decoded = json_decode($value, true);
                    $convertedValue = $decoded !== null ? $decoded : $value;
                    break;
            }
        }

        return [
            'id' => $setting->getId(),
            'key' => $setting->getKey(),
            'value' => $convertedValue,
            'valueType' => $setting->getValueType(),
            'description' => $setting->getDescription(),
            'category' => $setting->getCategory(),
            'isPublic' => $setting->isPublic(),
            'createdAt' => $setting->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $setting->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Récupérer un paramètre public par query parameter
     * Compatible avec l'endpoint API Platform mais accessible publiquement
     * Route: /api/site_settings?key=maintenance_mode
     * Priority élevée pour être évaluée avant la route API Platform
     */
    #[Route('/site_settings', name: 'get_by_query', methods: ['GET'], priority: 10)]
    public function getByQuery(Request $request): JsonResponse
    {
        $key = $request->query->get('key');

        if (!$key) {
            // Si pas de clé, retourner tous les paramètres publics
            $settings = $this->repository->findPublicSettings();
            
            $data = array_map(fn($setting) => $this->settingToArray($setting), $settings);

            return new JsonResponse($data, Response::HTTP_OK);
        }

        $setting = $this->repository->findByKey($key);

        if (!$setting) {
            return new JsonResponse(
                ['message' => 'Paramètre non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Vérifier que le paramètre est public
        if (!$setting->isPublic()) {
            return new JsonResponse(
                ['message' => 'Paramètre non accessible publiquement'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse($this->settingToArray($setting), Response::HTTP_OK);
    }

    /**
     * Récupérer tous les paramètres publics
     * Route: /api/site-settings/public
     */
    #[Route('/site-settings/public', name: 'get_all', methods: ['GET'])]
    public function getAllPublic(): JsonResponse
    {
        $settings = $this->repository->findPublicSettings();
        
        $data = array_map(fn($setting) => $this->settingToArray($setting), $settings);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * Récupérer un paramètre public par sa clé
     * Route: /api/site-settings/public/{key}
     */
    #[Route('/site-settings/public/{key}', name: 'get_by_key', methods: ['GET'])]
    public function getByKey(string $key): JsonResponse
    {
        $setting = $this->repository->findByKey($key);

        if (!$setting) {
            return new JsonResponse(
                ['message' => 'Paramètre non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Vérifier que le paramètre est public
        if (!$setting->isPublic()) {
            return new JsonResponse(
                ['message' => 'Paramètre non accessible publiquement'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse($this->settingToArray($setting), Response::HTTP_OK);
    }
}
