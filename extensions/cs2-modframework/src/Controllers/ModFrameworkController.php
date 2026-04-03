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

class ModFrameworkController extends Controller
{
    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
        private readonly GitHubReleaseResolver $releaseResolver,
    ) {
    }

    private function createInstaller(Server $server): FrameworkInstaller
    {
        $repo = clone $this->fileRepository;
        $repo->setServer($server);

        return new FrameworkInstaller(
            $repo,
            $this->releaseResolver,
            new GameInfoModifier($repo),
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

    public function status(Request $request, string $server): JsonResponse
    {
        try {
            $serverModel = $this->resolveServer($request);
            $installer = $this->createInstaller($serverModel);

            return response()->json([
                'data' => $installer->getStatus(),
            ]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] status error: {$e->getMessage()}", [
                'server' => $server,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Failed to fetch framework status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function versions(Request $request, string $server): JsonResponse
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

    public function install(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        try {
            $serverModel = $this->resolveServer($request);
            $installer = $this->createInstaller($serverModel);

            $result = $installer->install($request->input('framework'));

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] install error: {$e->getMessage()}", [
                'server' => $server,
                'framework' => $request->input('framework'),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uninstall(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        try {
            $serverModel = $this->resolveServer($request);
            $installer = $this->createInstaller($serverModel);

            $result = $installer->uninstall($request->input('framework'));

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            Log::error("[Notur cs2-modframework] uninstall error: {$e->getMessage()}", [
                'server' => $server,
                'framework' => $request->input('framework'),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Uninstallation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
