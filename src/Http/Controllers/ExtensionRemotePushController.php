<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Notur\ExtensionManifest;
use Notur\Support\NoturArchive;

class ExtensionRemotePushController extends Controller
{
    public function push(Request $request): JsonResponse
    {
        $maxUploadKb = max(1, (int) config('notur.remote_push.max_upload_mb', 50)) * 1024;

        $request->validate([
            'extension' => ['required', 'file', 'max:' . $maxUploadKb],
            'signature' => ['nullable', 'file', 'max:64'],
        ]);

        $upload = $request->file('extension');
        if ($upload === null || !$upload->isValid()) {
            return response()->json(['message' => 'Invalid extension upload.'], 422);
        }

        $tmpDir = sys_get_temp_dir() . '/notur-remote-' . uniqid('', true);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $archivePath = $tmpDir . '/extension.notur';
        $upload->move($tmpDir, 'extension.notur');

        $signature = $request->file('signature');
        if ($signature !== null && $signature->isValid()) {
            $signature->move($tmpDir, 'extension.notur.sig');
        }

        $manifest = $this->readManifest($archivePath);
        if (!$manifest instanceof ExtensionManifest) {
            $this->removeDirectory($tmpDir);
            return response()->json(['message' => 'Uploaded archive does not contain a valid extension manifest.'], 422);
        }

        $force = $request->boolean('force', true);

        try {
            $exitCode = Artisan::call('notur:add', array_filter([
                'extension' => $archivePath,
                '--force' => $force,
            ], static fn ($value) => $value !== false));

            $output = Artisan::output();
        } finally {
            $this->removeDirectory($tmpDir);
        }

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Remote extension install failed.',
                'id' => $manifest->getId(),
                'version' => $manifest->getVersion(),
                'output' => $output ?? '',
            ], 422);
        }

        return response()->json([
            'message' => 'Extension pushed and installed.',
            'id' => $manifest->getId(),
            'version' => $manifest->getVersion(),
            'output' => $output ?? '',
        ]);
    }

    private function readManifest(string $archivePath): ?ExtensionManifest
    {
        $tmpDir = sys_get_temp_dir() . '/notur-remote-manifest-' . uniqid('', true);

        try {
            NoturArchive::unpack($archivePath, $tmpDir, true, true);
            return ExtensionManifest::load($tmpDir);
        } catch (\Throwable) {
            return null;
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
