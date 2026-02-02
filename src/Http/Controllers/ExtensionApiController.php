<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Notur\ExtensionManager;

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
