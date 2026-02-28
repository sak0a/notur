<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Notur\ExtensionManager;
use Notur\Models\ExtensionActivity;
use Notur\Models\ExtensionSetting;
use Notur\Models\InstalledExtension;
use Notur\Support\RegistryClient;
use Notur\Support\SystemDiagnostics;
use Notur\Support\SlotDefinitions;

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
        $settingsSchema = $this->buildSettingsSchema($manifest);
        $settingsValues = $this->loadSettingsValues($extensionId, $settingsSchema['fields']);

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

        // Gather health checks
        $healthDefinitions = $manifest['health']['checks'] ?? [];
        if (!is_array($healthDefinitions)) {
            $healthDefinitions = [];
        }
        $healthResults = $this->manager->getHealthChecks($extensionId);
        $healthResultMap = [];
        foreach ($healthResults as $result) {
            if (!empty($result['id'])) {
                $healthResultMap[$result['id']] = $result;
            }
        }

        // Gather scheduled tasks
        $scheduleTasks = $manifest['schedules']['tasks'] ?? [];
        if (!is_array($scheduleTasks)) {
            $scheduleTasks = [];
        }

        // Gather frontend slot registrations
        $slotRegistrations = [];
        $slotRegistrationSource = 'none';
        $slotsByExtension = $this->manager->getFrontendSlots();
        if (isset($slotsByExtension[$extensionId]) && is_array($slotsByExtension[$extensionId])) {
            foreach ($slotsByExtension[$extensionId] as $slotId => $slotConfig) {
                $slotRegistrations[] = [
                    'slot' => $slotId,
                    'component' => is_array($slotConfig) ? ($slotConfig['component'] ?? null) : null,
                    'label' => is_array($slotConfig) ? ($slotConfig['label'] ?? null) : null,
                    'icon' => is_array($slotConfig) ? ($slotConfig['icon'] ?? null) : null,
                    'order' => is_array($slotConfig) ? ($slotConfig['order'] ?? null) : null,
                    'priority' => is_array($slotConfig) ? ($slotConfig['priority'] ?? null) : null,
                    'permission' => is_array($slotConfig) ? ($slotConfig['permission'] ?? null) : null,
                    'props' => is_array($slotConfig) ? ($slotConfig['props'] ?? null) : null,
                    'when' => is_array($slotConfig) ? ($slotConfig['when'] ?? null) : null,
                ];
            }

            if ($slotRegistrations !== []) {
                $slotRegistrationSource = 'backend';
            }
        }

        if ($slotRegistrations === []) {
            $manifestSlots = $manifest['frontend']['slots'] ?? [];
            if (is_array($manifestSlots)) {
                foreach ($manifestSlots as $slotId => $slotConfig) {
                    if (!is_string($slotId)) {
                        continue;
                    }

                    $slotRegistrations[] = [
                        'slot' => $slotId,
                        'component' => is_array($slotConfig) ? ($slotConfig['component'] ?? null) : null,
                        'label' => is_array($slotConfig) ? ($slotConfig['label'] ?? null) : null,
                        'icon' => is_array($slotConfig) ? ($slotConfig['icon'] ?? null) : null,
                        'order' => is_array($slotConfig) ? ($slotConfig['order'] ?? null) : null,
                        'priority' => is_array($slotConfig) ? ($slotConfig['priority'] ?? null) : null,
                        'permission' => is_array($slotConfig) ? ($slotConfig['permission'] ?? null) : null,
                        'props' => is_array($slotConfig) ? ($slotConfig['props'] ?? null) : null,
                        'when' => is_array($slotConfig) ? ($slotConfig['when'] ?? null) : null,
                    ];
                }

                if ($slotRegistrations !== []) {
                    $slotRegistrationSource = 'manifest';
                }
            }
        }

        // Gather admin routes
        $adminRoutes = [];
        $adminPrefix = "admin/notur/{$extensionId}";
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, $adminPrefix)) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $adminRoutes[] = [
                'methods' => $methods,
                'uri' => '/' . $uri,
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        }

        $adminRouteFile = $manifest['backend']['routes']['admin'] ?? null;
        $activityLogs = ExtensionActivity::where('extension_id', $extensionId)
            ->latest()
            ->limit(50)
            ->get();

        return view('notur::admin.extension-detail', [
            'extension' => $extension,
            'manifest' => $manifest,
            'migrationStatus' => $migrationStatus,
            'permissions' => $permissions,
            'dependencies' => $dependencies,
            'settingsSchema' => $settingsSchema,
            'settingsValues' => $settingsValues,
            'slotRegistrations' => $slotRegistrations,
            'slotRegistrationSource' => $slotRegistrationSource,
            'adminRoutes' => $adminRoutes,
            'adminRouteFile' => $adminRouteFile,
            'activityLogs' => $activityLogs,
            'healthDefinitions' => $healthDefinitions,
            'healthResults' => $healthResults,
            'healthResultMap' => $healthResultMap,
            'scheduleTasks' => $scheduleTasks,
        ]);
    }

    /**
     * Show the slot metadata/preview page.
     */
    public function slots(): View
    {
        $definitions = SlotDefinitions::all();
        $definitionMap = SlotDefinitions::map();
        $registrations = $this->manager->getFrontendSlots();
        $usage = [];
        $unknown = [];

        foreach ($definitions as $def) {
            $usage[$def['id']] = [];
        }

        foreach ($registrations as $extensionId => $slots) {
            if (!is_array($slots)) {
                continue;
            }

            foreach ($slots as $slotId => $slotConfig) {
                    $entry = [
                        'extensionId' => $extensionId,
                        'component' => is_array($slotConfig) ? ($slotConfig['component'] ?? null) : null,
                        'label' => is_array($slotConfig) ? ($slotConfig['label'] ?? null) : null,
                        'order' => is_array($slotConfig) ? ($slotConfig['order'] ?? null) : null,
                        'priority' => is_array($slotConfig) ? ($slotConfig['priority'] ?? null) : null,
                        'permission' => is_array($slotConfig) ? ($slotConfig['permission'] ?? null) : null,
                        'props' => is_array($slotConfig) ? ($slotConfig['props'] ?? null) : null,
                        'when' => is_array($slotConfig) ? ($slotConfig['when'] ?? null) : null,
                    ];

                if (isset($definitionMap[$slotId])) {
                    $usage[$slotId][] = $entry;
                } else {
                    $unknown[$slotId][] = $entry;
                }
            }
        }

        return view('notur::admin.slots', [
            'definitions' => $definitions,
            'usage' => $usage,
            'unknown' => $unknown,
        ]);
    }

    /**
     * Show the frontend diagnostics page.
     */
    public function diagnostics(SystemDiagnostics $diagnostics): View
    {
        return view('notur::admin.diagnostics', [
            'generalInfo' => $diagnostics->summary(),
        ]);
    }

    /**
     * Show the extension health overview page.
     */
    public function health(): View
    {
        $extensions = InstalledExtension::all();
        $healthData = [];

        foreach ($extensions as $extension) {
            $manifest = $extension->manifest ?? [];
            $healthDefinitions = $manifest['health']['checks'] ?? [];
            if (!is_array($healthDefinitions)) {
                $healthDefinitions = [];
            }

            $healthResults = $this->manager->getHealthChecks($extension->extension_id);
            $healthResultMap = [];
            foreach ($healthResults as $result) {
                if (!empty($result['id'])) {
                    $healthResultMap[$result['id']] = $result;
                }
            }

            $statusCounts = [
                'ok' => 0,
                'warning' => 0,
                'error' => 0,
                'unknown' => 0,
            ];
            $criticalFailures = 0;

            foreach ($healthDefinitions as $definition) {
                $checkId = $definition['id'] ?? null;
                if (!$checkId) {
                    continue;
                }

                $result = $healthResultMap[$checkId] ?? null;
                $status = $result['status'] ?? 'unknown';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

                $severity = strtolower((string) ($definition['severity'] ?? ''));
                if ($severity === 'critical' && in_array($status, ['warning', 'error'], true)) {
                    $criticalFailures++;
                }
            }

            $healthData[] = [
                'extension' => $extension,
                'manifest' => $manifest,
                'healthDefinitions' => $healthDefinitions,
                'healthResults' => $healthResults,
                'healthResultMap' => $healthResultMap,
                'statusCounts' => $statusCounts,
                'criticalFailures' => $criticalFailures,
            ];
        }

        return view('notur::admin.health', [
            'healthData' => $healthData,
        ]);
    }

    /**
     * Save extension settings from the admin UI.
     */
    public function updateSettings(Request $request, string $extensionId): RedirectResponse
    {
        $extension = InstalledExtension::where('extension_id', $extensionId)->firstOrFail();
        $manifest = $extension->manifest ?? [];
        $schema = $this->buildSettingsSchema($manifest);
        $fields = $schema['fields'];

        if (empty($fields)) {
            return redirect()
                ->back()
                ->with('error', 'This extension does not define any settings.');
        }

        $errors = [];
        $valuesToSave = [];
        $settingsInput = $request->input('settings', []);
        if (!is_array($settingsInput)) {
            $settingsInput = [];
        }

        foreach ($fields as $field) {
            $key = $field['key'];
            $type = $field['type'];

            if ($type === 'boolean') {
                $value = !empty($settingsInput[$key]);
            } else {
                $value = $settingsInput[$key] ?? null;
            }

            if (($value === null || $value === '') && $field['required'] && $type !== 'boolean') {
                $errors[$key] = 'This field is required.';
                continue;
            }

            if ($type === 'number') {
                if ($value === null || $value === '') {
                    $value = null;
                } elseif (!is_numeric($value)) {
                    $errors[$key] = 'This field must be a number.';
                    continue;
                } else {
                    $value = $value + 0;
                }
            }

            if ($type === 'select') {
                $optionValues = array_map(
                    static fn ($option) => (string) $option['value'],
                    $field['options'] ?? [],
                );

                if ($value === null || $value === '') {
                    $value = null;
                } elseif (!in_array((string) $value, $optionValues, true)) {
                    $errors[$key] = 'Invalid selection.';
                    continue;
                }
            }

            if ($type === 'string' || $type === 'text') {
                if ($value !== null) {
                    $value = (string) $value;
                }
            }

            $valuesToSave[$key] = $value;
        }

        if (!empty($errors)) {
            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        }

        foreach ($valuesToSave as $key => $value) {
            if ($value === null || $value === '') {
                ExtensionSetting::where('extension_id', $extensionId)
                    ->where('key', $key)
                    ->delete();
                continue;
            }

            ExtensionSetting::setValue($extensionId, $key, $value);
        }

        return redirect()
            ->back()
            ->with('success', 'Settings saved.');
    }

    /**
     * Preview the settings schema and current values for an extension.
     */
    public function settingsPreview(string $extensionId): JsonResponse
    {
        $extension = InstalledExtension::where('extension_id', $extensionId)->first();
        if (!$extension) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $manifest = $extension->manifest ?? [];
        $schema = $this->buildSettingsSchema($manifest);
        $values = $this->loadSettingsValues($extensionId, $schema['fields']);

        return response()->json([
            'data' => [
                'extension' => [
                    'id' => $extension->extension_id,
                    'name' => $extension->name,
                    'version' => $extension->version,
                ],
                'schema' => $schema,
                'values' => $values,
            ],
        ]);
    }

    /**
     * Install an extension from registry ID or uploaded file.
     */
    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'registry_id' => ['nullable', 'string', 'regex:/^[a-z0-9\\-]+\\/[a-z0-9\\-]+$/'],
            'archive' => ['nullable', 'file', 'max:51200', 'extensions:notur'],
        ], [
            'registry_id.regex' => 'Registry ID must be in vendor/name format.',
            'archive.extensions' => 'Archive must be a .notur file.',
            'archive.max' => 'Archive may not be greater than 50 MB.',
        ]);

        $registryId = isset($validated['registry_id']) ? trim((string) $validated['registry_id']) : '';
        $uploadedFile = $request->file('archive');

        if (!$registryId && !$uploadedFile) {
            return redirect()
                ->route('admin.notur.extensions')
                ->with('error', 'Please provide a registry ID or upload a .notur file.');
        }

        $tmpPath = null;

        try {
            if ($uploadedFile) {
                $tmpPath = sys_get_temp_dir() . '/notur-upload-' . uniqid('', true) . '.notur';
                $uploadedFile->move(dirname($tmpPath), basename($tmpPath));

                $exitCode = Artisan::call('notur:install', [
                    'extension' => $tmpPath,
                    '--force' => true,
                ]);
            } else {
                $exitCode = Artisan::call('notur:install', [
                    'extension' => $registryId,
                    '--force' => true,
                ]);
            }

            $output = Artisan::output();
            if ($exitCode !== 0) {
                return redirect()
                    ->route('admin.notur.extensions')
                    ->with('error', 'Installation failed: ' . trim($output));
            }

            return redirect()
                ->route('admin.notur.extensions')
                ->with('success', 'Extension installed successfully. ' . trim($output));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.notur.extensions')
                ->with('error', 'Installation failed: ' . $e->getMessage());
        } finally {
            if (is_string($tmpPath) && $tmpPath !== '' && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            if (is_string($tmpPath) && $tmpPath !== '' && file_exists($tmpPath . '.sig')) {
                @unlink($tmpPath . '.sig');
            }
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

    private function buildSettingsSchema(array $manifest): array
    {
        $settings = $manifest['admin']['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $fieldsRaw = $settings['fields'] ?? [];
        if (!is_array($fieldsRaw)) {
            $fieldsRaw = [];
        }

        $fields = [];

        foreach ($fieldsRaw as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? '';
            if (!is_string($key) || $key === '') {
                continue;
            }

            $type = $field['type'] ?? 'string';
            if (!in_array($type, ['string', 'text', 'boolean', 'number', 'select'], true)) {
                $type = 'string';
            }

            $fields[] = [
                'key' => $key,
                'label' => $field['label'] ?? $this->labelFromKey($key),
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'default' => $field['default'] ?? null,
                'help' => $field['help'] ?? ($field['description'] ?? null),
                'placeholder' => $field['placeholder'] ?? null,
                'options' => $this->normalizeOptions($field['options'] ?? []),
                'input' => $field['input'] ?? null,
                'public' => (bool) ($field['public'] ?? false),
            ];
        }

        return [
            'title' => $settings['title'] ?? 'Settings',
            'description' => $settings['description'] ?? null,
            'fields' => $fields,
        ];
    }

    private function loadSettingsValues(string $extensionId, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $default = $field['default'] ?? null;
            $values[$key] = ExtensionSetting::getValue($extensionId, $key, $default);
        }

        return $values;
    }

    private function normalizeOptions(mixed $options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];

        if (array_is_list($options)) {
            foreach ($options as $option) {
                if (is_array($option)) {
                    $value = $option['value'] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    $normalized[] = [
                        'value' => (string) $value,
                        'label' => (string) ($option['label'] ?? $value),
                    ];
                } else {
                    $normalized[] = [
                        'value' => (string) $option,
                        'label' => (string) $option,
                    ];
                }
            }

            return $normalized;
        }

        foreach ($options as $value => $label) {
            $normalized[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $normalized;
    }

    private function labelFromKey(string $key): string
    {
        $label = str_replace(['.', '-', '_'], ' ', $key);
        return ucwords($label);
    }
}
