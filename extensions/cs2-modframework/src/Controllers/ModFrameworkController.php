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
        $serverModel = $request->attributes->get('server');
        $installer = $this->createInstaller($serverModel);

        return response()->json([
            'data' => $installer->getStatus(),
        ]);
    }

    public function versions(Request $request, string $server): JsonResponse
    {
        return response()->json([
            'data' => $this->releaseResolver->getLatestVersions(),
        ]);
    }

    public function install(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        $serverModel = $request->attributes->get('server');
        $installer = $this->createInstaller($serverModel);

        $result = $installer->install($request->input('framework'));

        return response()->json(['data' => $result]);
    }

    public function uninstall(Request $request, string $server): JsonResponse
    {
        $request->validate([
            'framework' => 'required|string|in:swiftly,counterstrikesharp,metamod',
        ]);

        $serverModel = $request->attributes->get('server');
        $installer = $this->createInstaller($serverModel);

        $result = $installer->uninstall($request->input('framework'));

        return response()->json(['data' => $result]);
    }
}
