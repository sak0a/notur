<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Notur\ExtensionManager;
use Notur\Models\InstalledExtension;
use Notur\Support\RegistryClient;

class ExtensionAdminController extends Controller
{
    public function __construct(
        private readonly ExtensionManager $manager,
    ) {}

    /**
     * Show the extension management page.
     */
    public function index(Request $request, RegistryClient $registry): View
    {
        $extensions = InstalledExtension::all();
        $installedIds = $extensions->pluck('extension_id')->all();
        $query = trim((string) $request->query('q', ''));
        $registryResults = [];
        $registryError = null;

        if ($query !== '') {
            try {
                $registryResults = $registry->search($query);
            } catch (\Throwable $e) {
                $registryError = $e->getMessage();
            }
        }

        return view('notur::admin.extensions', [
            'extensions' => $extensions,
            'installedIds' => $installedIds,
            'registryQuery' => $query,
            'registryResults' => $registryResults,
            'registryError' => $registryError,
        ]);
    }

    /**
     * Show details for a single extension.
     */
    public function show(string $extensionId): View
    {
        $extension = InstalledExtension::where('extension_id', $extensionId)->firstOrFail();
        $manifest = $extension->manifest ?? [];

        // Gather migration status
        $migrationStatus = [];
        $migrationsPath = $manifest['backend']['migrations'] ?? '';
        if ($migrationsPath) {
            $fullPath = $this->manager->getExtensionsPath()
                . '/' . str_replace('/', DIRECTORY_SEPARATOR, $extensionId)
                . '/' . $migrationsPath;

            if (is_dir($fullPath)) {
                $files = glob($fullPath . '/*.php');
                foreach ($files ?: [] as $file) {
                    $migrationStatus[] = basename($file);
                }
            }
        }

        // Gather permissions
        $permissions = $manifest['backend']['permissions'] ?? [];

        // Gather dependencies
        $dependencies = $manifest['dependencies'] ?? [];

        return view('notur::admin.extension-detail', [
            'extension' => $extension,
            'manifest' => $manifest,
            'migrationStatus' => $migrationStatus,
            'permissions' => $permissions,
            'dependencies' => $dependencies,
        ]);
    }

    /**
     * Install an extension from registry ID or uploaded file.
     */
    public function install(Request $request): RedirectResponse
    {
        $registryId = $request->input('registry_id');
        $uploadedFile = $request->file('archive');

        if (!$registryId && !$uploadedFile) {
            return redirect()
                ->route('admin.notur.extensions')
                ->with('error', 'Please provide a registry ID or upload a .notur file.');
        }

        try {
            if ($uploadedFile) {
                $tmpPath = sys_get_temp_dir() . '/notur-upload-' . uniqid() . '.notur';
                $uploadedFile->move(dirname($tmpPath), basename($tmpPath));

                Artisan::call('notur:install', [
                    'extension' => $tmpPath,
                    '--force' => true,
                ]);
            } else {
                Artisan::call('notur:install', [
                    'extension' => $registryId,
                    '--force' => true,
                ]);
            }

            $output = Artisan::output();

            return redirect()
                ->route('admin.notur.extensions')
                ->with('success', 'Extension installed successfully. ' . trim($output));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.notur.extensions')
                ->with('error', 'Installation failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove an extension.
     */
    public function remove(string $extensionId): RedirectResponse
    {
        try {
            Artisan::call('notur:remove', [
                'extension' => $extensionId,
                '--no-interaction' => true,
            ]);

            return redirect()
                ->route('admin.notur.extensions')
                ->with('success', "Extension '{$extensionId}' has been removed.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.notur.extensions')
                ->with('error', 'Removal failed: ' . $e->getMessage());
        }
    }

    /**
     * Enable an extension.
     */
    public function enable(string $extensionId): RedirectResponse
    {
        $this->manager->enable($extensionId);

        return redirect()
            ->route('admin.notur.extensions')
            ->with('success', "Extension '{$extensionId}' has been enabled.");
    }

    /**
     * Disable an extension.
     */
    public function disable(string $extensionId): RedirectResponse
    {
        $this->manager->disable($extensionId);

        return redirect()
            ->route('admin.notur.extensions')
            ->with('success', "Extension '{$extensionId}' has been disabled.");
    }
}
