<?php

declare(strict_types=1);

namespace Notur\Cs2Modframework\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

    private function resolveServer(Request $request, string $uuid): Server
    {
        // Support both short UUID (from URL) and full UUID (from data attribute)
        $server = Server::where(function ($query) use ($uuid) {
                $query->where('uuid', $uuid)->orWhere('uuidShort', $uuid);
            })
            ->where('suspended', false)
            ->firstOrFail();

        // Verify the authenticated user has access to this server
        $user = $request->user();
        if ($user && !$user->root_admin) {
            $hasAccess = $server->owner_id === $user->id
                || $server->subusers()->where('user_id', $user->id)->exists();

            if (!$hasAccess) {
                abort(403, 'You do not have access to this server.');
            }
        }

        return $server;
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

    public function status(Request $request, string $server): JsonResponse
    {
        $serverModel = $this->resolveServer($request, $server);
        $installer = $this->createInstaller($serverModel);

        return response()->json([
            'data' => $installer->getStatus(),
        ]);
    }

    public function versions(Request $request, string $server): JsonResponse
    {
        // Validate server access even though versions are server-independent
        $this->resolveServer($request, $server);

        return response()->json([
            'data' => $this->releaseResolver->getLatestVersions(),
        ]);
    }

    public function install(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        $serverModel = $this->resolveServer($request, $server);
        $installer = $this->createInstaller($serverModel);

        $result = $installer->install($request->input('framework'));

        return response()->json(['data' => $result]);
    }

    public function uninstall(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        $serverModel = $this->resolveServer($request, $server);
        $installer = $this->createInstaller($serverModel);

        $result = $installer->uninstall($request->input('framework'));

        return response()->json(['data' => $result]);
    }
}
