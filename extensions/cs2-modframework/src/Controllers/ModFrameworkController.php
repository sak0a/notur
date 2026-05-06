<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Notur\Cs2Modframework\Services\FrameworkInstaller;
use Notur\Cs2Modframework\Services\GitHubReleaseResolver;
use Notur\Cs2Modframework\Services\GameInfoModifier;
use Notur\Cs2Modframework\Services\ServerEligibility;

class ModFrameworkController extends Controller
{
    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
        private readonly GitHubReleaseResolver $releaseResolver,
        private readonly ServerEligibility $eligibility,
    ) {
    }

    private function createInstaller(Server $server, ?array $eligibility = null): FrameworkInstaller
    {
        $repo = clone $this->fileRepository;
        $repo->setServer($server);

        return new FrameworkInstaller(
            $repo,
            $this->releaseResolver,
            new GameInfoModifier($repo),
            $eligibility ?? $this->eligibility->check($server),
        );
    }

    /**
     * Resolve the server model from the request, set by VerifyServerAccess middleware.
     */
    private function resolveServer(Request $request): Server
    {
        $server = $request->attributes->get('server');

        if (!$server instanceof Server) {
            abort(500, 'Server context not available. The notur.server-access middleware may not be applied to this route.');
        }

        return $server;
    }

    public function status(Request $request, string $serverUuid): JsonResponse
    {
        try {
            $serverModel = $this->resolveServer($request);
            $eligibility = $this->eligibility->check($serverModel);
            $installer = $this->createInstaller($serverModel, $eligibility);

            return response()->json([
                'data' => $installer->getStatus() + [
                    'server' => [
                        'supported' => $eligibility['supported'],
                        'reason' => $eligibility['reason'],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] status error: {$e->getMessage()}", [
                'server' => $serverUuid,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Failed to fetch framework status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function versions(Request $request, string $serverUuid): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->releaseResolver->getLatestVersions(),
            ]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] versions error: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Failed to fetch versions: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function install(Request $request, string $serverUuid): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
            'version' => 'nullable|string|max:64',
        ]);

        try {
            $serverModel = $this->resolveServer($request);
            $eligibility = $this->eligibility->check($serverModel);
            if (!$eligibility['supported']) {
                return response()->json([
                    'message' => $eligibility['reason'] ?? 'This server is not eligible for CS2 mod framework installation.',
                ], 422);
            }

            $installer = $this->createInstaller($serverModel, $eligibility);

            $version = $request->filled('version') ? trim((string) $request->input('version')) : null;
            $result = $installer->install($request->input('framework'), $version);

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] install error: {$e->getMessage()}", [
                'server' => $serverUuid,
                'framework' => $request->input('framework'),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uninstall(Request $request, string $serverUuid): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        try {
            $serverModel = $this->resolveServer($request);
            $eligibility = $this->eligibility->check($serverModel);
            if (!$eligibility['supported']) {
                return response()->json([
                    'message' => $eligibility['reason'] ?? 'This server is not eligible for CS2 mod framework management.',
                ], 422);
            }

            $installer = $this->createInstaller($serverModel, $eligibility);

            $result = $installer->uninstall($request->input('framework'));

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] uninstall error: {$e->getMessage()}", [
                'server' => $serverUuid,
                'framework' => $request->input('framework'),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Uninstallation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
