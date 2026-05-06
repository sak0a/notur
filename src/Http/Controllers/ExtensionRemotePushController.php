<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Notur\ExtensionManifest;
use Notur\Models\InstalledExtension;
use Notur\Models\RemotePushApiKey;
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

        $checksum = hash_file('sha256', $archivePath) ?: null;
        $size = @filesize($archivePath) ?: null;

        $manifest = $this->readManifest($archivePath);
        if (!$manifest instanceof ExtensionManifest) {
            $this->removeDirectory($tmpDir);
            return response()->json(['message' => 'Uploaded archive does not contain a valid extension manifest.'], 422);
        }

        $force = $request->boolean('force', true);
        $key = $request->attributes->get('notur_remote_push_key');
        $extensionId = $manifest->getId();

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
            $this->recordFailure($extensionId, $output);

            return response()->json([
                'message' => 'Remote extension install failed.',
                'id' => $extensionId,
                'version' => $manifest->getVersion(),
                'output' => $output ?? '',
            ], 422);
        }

        $this->recordSuccess($extensionId, $key instanceof RemotePushApiKey ? $key : null, $checksum, $size);

        return response()->json([
            'message' => 'Extension pushed and installed.',
            'id' => $extensionId,
            'version' => $manifest->getVersion(),
            'output' => $output ?? '',
        ]);
    }

    public function recordSuccess(string $extensionId, ?RemotePushApiKey $key, ?string $checksum, ?int $size): void
    {
        $row = InstalledExtension::where('extension_id', $extensionId)->first();
        if ($row === null) {
            return;
        }

        $row->forceFill([
            'source' => 'remote_push',
            'pushed_via_key_id' => $key?->id,
            'last_pushed_at' => now(),
            'package_checksum' => $checksum,
            'package_size' => $size,
            'last_push_error' => null,
        ])->save();
    }

    public function recordFailure(string $extensionId, ?string $output): void
    {
        $row = InstalledExtension::where('extension_id', $extensionId)->first();
        if ($row === null) {
            return;
        }

        $row->forceFill([
            'last_push_error' => is_string($output) && trim($output) !== '' ? trim($output) : 'Push failed.',
        ])->save();
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
