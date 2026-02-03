<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Notur\ExtensionManager;
use Notur\Models\ExtensionSetting;

class ExtensionApiController extends Controller
{
    public function __construct(
        private readonly ExtensionManager $manager,
    ) {}

    /**
     * Return the frontend slot manifest for all enabled extensions.
     */
    public function slots(): JsonResponse
    {
        return response()->json([
            'data' => $this->manager->getFrontendSlots(),
        ]);
    }

    /**
     * Return metadata for all enabled extensions.
     */
    public function extensions(): JsonResponse
    {
        $extensions = [];

        foreach ($this->manager->all() as $id => $extension) {
            $manifest = $this->manager->getManifest($id);
            $extensions[] = [
                'id' => $id,
                'name' => $extension->getName(),
                'version' => $extension->getVersion(),
                'description' => $manifest?->getDescription() ?? '',
                'slots' => $manifest?->getFrontendSlots() ?? [],
            ];
        }

        return response()->json(['data' => $extensions]);
    }

    /**
     * Return public settings for a specific extension.
     */
    public function settings(string $extensionId): JsonResponse
    {
        $manifest = $this->manager->getManifest($extensionId);

        if (!$manifest) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $adminConfig = $manifest->getAdminConfig();
        $settingsConfig = is_array($adminConfig) ? ($adminConfig['settings'] ?? []) : [];
        $fields = is_array($settingsConfig['fields'] ?? null) ? $settingsConfig['fields'] : [];

        $publicSettings = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $isPublic = (bool) ($field['public'] ?? false);
            if (!$isPublic) {
                continue;
            }

            $default = $field['default'] ?? null;
            $publicSettings[$key] = ExtensionSetting::getValue($extensionId, $key, $default);
        }

        return response()->json(['data' => $publicSettings]);
    }

    /**
     * Return the Notur configuration for the frontend bridge.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'version' => config('notur.version'),
            'slots' => $this->manager->getFrontendSlots(),
        ]);
    }
}
