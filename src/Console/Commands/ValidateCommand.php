<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\Support\SchemaValidator;
use Symfony\Component\Yaml\Yaml;

class ValidateCommand extends Command
{
    protected $signature = 'notur:validate {path? : Path to the extension (defaults to current directory)} {--strict : Treat warnings as errors}';

    protected $description = 'Validate a Notur extension manifest and settings schema';

    public function handle(): int
    {
        $path = realpath($this->argument('path') ?? getcwd());

        if (!$path || !is_dir($path)) {
            $this->error('Path does not exist or is not a directory.');
            return 1;
        }

        $manifestPath = $this->findManifestPath($path);
        if (!$manifestPath) {
            $this->error('No extension.yaml or extension.yml found.');
            return 1;
        }

        try {
            $manifest = Yaml::parseFile($manifestPath);
        } catch (\Throwable $e) {
            $this->error('Failed to parse manifest: ' . $e->getMessage());
            return 1;
        }

        if (!is_array($manifest)) {
            $this->error('Manifest content is invalid.');
            return 1;
        }

        $errors = SchemaValidator::validateManifest($manifest);

        [$settingsErrors, $settingsWarnings] = $this->validateSettingsSchema($manifest);
        $errors = array_merge($errors, $settingsErrors);

        [$capErrors, $capWarnings] = $this->validateCapabilities($manifest);
        $errors = array_merge($errors, $capErrors);

        [$scheduleErrors, $scheduleWarnings] = $this->validateSchedules($manifest);
        $errors = array_merge($errors, $scheduleErrors);

        [$entryErrors, $entryWarnings] = $this->validateEntrypoint($manifest, $path);
        $errors = array_merge($errors, $entryErrors);

        if (!empty($errors)) {
            $this->error('Validation failed:');
            foreach ($errors as $error) {
                $this->line(' - ' . $error);
            }
            return 1;
        }

        $this->info('Manifest is valid.');

        $warnings = array_merge($settingsWarnings, $capWarnings, $scheduleWarnings, $entryWarnings);

        if (!empty($warnings)) {
            $this->warn('Warnings:');
            foreach ($warnings as $warning) {
                $this->line(' - ' . $warning);
            }
            if ($this->option('strict')) {
                $this->error('Strict mode enabled: warnings treated as errors.');
                return 1;
            }
        }

        return 0;
    }

    private function findManifestPath(string $path): ?string
    {
        $yaml = $path . '/extension.yaml';
        if (file_exists($yaml)) {
            return $yaml;
        }

        $yml = $path . '/extension.yml';
        if (file_exists($yml)) {
            return $yml;
        }

        return null;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function validateSettingsSchema(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        $settings = $manifest['admin']['settings'] ?? null;
        if ($settings === null) {
            return [$errors, $warnings];
        }

        if (!is_array($settings)) {
            $errors[] = 'admin.settings must be an object.';
            return [$errors, $warnings];
        }

        $fields = $settings['fields'] ?? [];
        if (!is_array($fields)) {
            $errors[] = 'admin.settings.fields must be an array.';
            return [$errors, $warnings];
        }

        if ($fields === []) {
            $warnings[] = 'admin.settings.fields is empty.';
            return [$errors, $warnings];
        }

        $allowedTypes = ['string', 'text', 'number', 'boolean', 'select'];
        $allowedInputs = ['text', 'email', 'password', 'url', 'color', 'number'];
        $seenKeys = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                $errors[] = "admin.settings.fields[{$index}] must be an object.";
                continue;
            }

            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                $errors[] = "admin.settings.fields[{$index}] is missing a valid key.";
                continue;
            }

            if (isset($seenKeys[$key])) {
                $errors[] = "admin.settings.fields contains duplicate key '{$key}'.";
            }
            $seenKeys[$key] = true;

            $type = $field['type'] ?? 'string';
            if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
                $errors[] = "admin.settings.fields[{$key}] has invalid type '{$type}'.";
                $type = 'string';
            }

            if (isset($field['required']) && !is_bool($field['required'])) {
                $errors[] = "admin.settings.fields[{$key}].required must be boolean.";
            }

            if (isset($field['public']) && !is_bool($field['public'])) {
                $errors[] = "admin.settings.fields[{$key}].public must be boolean.";
            }

            if (isset($field['label']) && !is_string($field['label'])) {
                $errors[] = "admin.settings.fields[{$key}].label must be a string.";
            }

            if (isset($field['help']) && !is_string($field['help'])) {
                $errors[] = "admin.settings.fields[{$key}].help must be a string.";
            }

            if (isset($field['description']) && !is_string($field['description'])) {
                $errors[] = "admin.settings.fields[{$key}].description must be a string.";
            }

            if (isset($field['placeholder']) && !is_string($field['placeholder'])) {
                $errors[] = "admin.settings.fields[{$key}].placeholder must be a string.";
            }

            if (isset($field['input'])) {
                if (!is_string($field['input']) || !in_array($field['input'], $allowedInputs, true)) {
                    $errors[] = "admin.settings.fields[{$key}].input must be one of: " . implode(', ', $allowedInputs);
                }
            }

            if (array_key_exists('default', $field)) {
                $default = $field['default'];
                if ($type === 'boolean' && !is_bool($default)) {
                    $errors[] = "admin.settings.fields[{$key}].default must be boolean.";
                } elseif ($type === 'number' && $default !== null && !is_numeric($default)) {
                    $errors[] = "admin.settings.fields[{$key}].default must be numeric.";
                } elseif (in_array($type, ['string', 'text'], true) && $default !== null && !is_scalar($default)) {
                    $errors[] = "admin.settings.fields[{$key}].default must be a string.";
                }
            }

            if ($type === 'select') {
                $options = $field['options'] ?? null;
                if (!is_array($options) || $options === []) {
                    $errors[] = "admin.settings.fields[{$key}].options must be a non-empty array.";
                } else {
                    $optionValues = [];
                    foreach ($options as $optIndex => $opt) {
                        if (is_array($opt)) {
                            $value = $opt['value'] ?? null;
                            if ($value === null || $value === '') {
                                $errors[] = "admin.settings.fields[{$key}].options[{$optIndex}] is missing a value.";
                                continue;
                            }
                            $optionValues[] = (string) $value;
                        } else {
                            $optionValues[] = (string) $opt;
                        }
                    }

                    if (array_key_exists('default', $field) && isset($field['default'])) {
                        $defaultValue = (string) $field['default'];
                        if (!in_array($defaultValue, $optionValues, true)) {
                            $errors[] = "admin.settings.fields[{$key}].default must match one of the select options.";
                        }
                    }
                }
            }
        }

        return [$errors, $warnings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function validateCapabilities(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        if (!array_key_exists('capabilities', $manifest)) {
            return [$errors, $warnings];
        }

        $capabilities = $manifest['capabilities'];
        if (!is_array($capabilities)) {
            $errors[] = 'capabilities must be an object.';
            return [$errors, $warnings];
        }

        if ($capabilities === []) {
            $warnings[] = 'capabilities is empty; all capability-gated features will be disabled.';
        }

        $supported = [
            'routes' => 1,
            'health' => 1,
            'schedules' => 1,
            'css_isolation' => 1,
        ];

        foreach ($capabilities as $id => $constraint) {
            if (!is_string($id) || $id === '') {
                $errors[] = 'capabilities keys must be non-empty strings.';
                continue;
            }

            if (!is_string($constraint) || $constraint === '') {
                $errors[] = "capabilities.{$id} must be a non-empty string.";
                continue;
            }

            if (!preg_match('/^(\\^|~|>=)?\\d+(?:\\.\\d+)?$/', $constraint)) {
                $warnings[] = "capabilities.{$id} uses an unrecognized version constraint '{$constraint}'.";
            }

            if (!isset($supported[$id])) {
                $warnings[] = "capabilities.{$id} is not a known Notur capability.";
                continue;
            }

            if (!\Notur\Support\CapabilityMatcher::matches($constraint, $supported[$id])) {
                $warnings[] = "capabilities.{$id} does not match the supported major version ({$supported[$id]}).";
            }
        }

        $usesRoutes = !empty($manifest['backend']['routes'] ?? []);
        if ($usesRoutes && !array_key_exists('routes', $capabilities)) {
            $warnings[] = 'backend.routes is defined but capabilities.routes is missing (routes will not be registered).';
        }

        $usesHealth = !empty($manifest['health']['checks'] ?? []);
        if ($usesHealth && !array_key_exists('health', $capabilities)) {
            $warnings[] = 'health.checks is defined but capabilities.health is missing (health checks will be ignored).';
        }

        $usesSchedules = !empty($manifest['schedules']['tasks'] ?? []);
        if ($usesSchedules && !array_key_exists('schedules', $capabilities)) {
            $warnings[] = 'schedules.tasks is defined but capabilities.schedules is missing (schedules will be ignored).';
        }

        $usesCssIsolation = !empty($manifest['frontend']['css_isolation'] ?? []);
        if ($usesCssIsolation && !array_key_exists('css_isolation', $capabilities)) {
            $warnings[] = 'frontend.css_isolation is defined but capabilities.css_isolation is missing (css isolation will be ignored).';
        }

        return [$errors, $warnings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function validateSchedules(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        $tasks = $manifest['schedules']['tasks'] ?? null;
        if ($tasks === null) {
            return [$errors, $warnings];
        }

        if (!is_array($tasks)) {
            $errors[] = 'schedules.tasks must be an array.';
            return [$errors, $warnings];
        }

        foreach ($tasks as $index => $task) {
            if (!is_array($task)) {
                $errors[] = "schedules.tasks[{$index}] must be an object.";
                continue;
            }

            $cron = $task['cron'] ?? null;
            $schedule = $task['schedule'] ?? null;

            if (($cron === null || $cron === '') && $schedule === null) {
                $errors[] = "schedules.tasks[{$index}] must define either cron or schedule.";
                continue;
            }

            if ($cron !== null && $schedule !== null) {
                $warnings[] = "schedules.tasks[{$index}] defines both cron and schedule; cron will take precedence.";
            }

            if ($schedule !== null) {
                if (!is_array($schedule)) {
                    $errors[] = "schedules.tasks[{$index}].schedule must be an object.";
                    continue;
                }

                $type = strtolower((string) ($schedule['type'] ?? ''));
                if ($type === '') {
                    $errors[] = "schedules.tasks[{$index}].schedule.type is required.";
                    continue;
                }

                if (in_array($type, ['dailyat', 'weeklyon'], true)) {
                    $at = $schedule['at'] ?? null;
                    if (!is_string($at) || !preg_match('/^\\d{2}:\\d{2}$/', $at)) {
                        $errors[] = "schedules.tasks[{$index}].schedule.at must be in HH:MM format.";
                    }
                }

                if ($type === 'weeklyon') {
                    $day = $schedule['day'] ?? null;
                    if ($this->parseDay($day) === null) {
                        $errors[] = "schedules.tasks[{$index}].schedule.day must be 0-6 or a weekday name.";
                    }
                }

                if (in_array($type, ['everyminutes', 'everyhours'], true)) {
                    $interval = $schedule['interval'] ?? null;
                    if ($this->parsePositiveInt($interval) === null) {
                        $errors[] = "schedules.tasks[{$index}].schedule.interval must be a positive integer.";
                    }
                }

                $knownTypes = ['hourly', 'daily', 'dailyat', 'weeklyon', 'everyminutes', 'everyhours'];
                if (!in_array($type, $knownTypes, true)) {
                    $warnings[] = "schedules.tasks[{$index}].schedule.type '{$type}' is not recognized.";
                }
            }
        }

        return [$errors, $warnings];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function validateEntrypoint(array $manifest, string $basePath): array
    {
        $errors = [];
        $warnings = [];

        $entrypoint = $manifest['entrypoint'] ?? null;
        if (is_string($entrypoint) && $entrypoint !== '') {
            return [$errors, $warnings];
        }

        $composerEntrypoint = $this->readComposerEntrypoint($basePath);
        if ($composerEntrypoint !== null) {
            return [$errors, $warnings];
        }

        $id = $manifest['id'] ?? null;
        if (!is_string($id) || $id === '' || !str_contains($id, '/')) {
            $warnings[] = 'entrypoint is missing and could not be inferred (invalid id).';
            return [$errors, $warnings];
        }

        [, $name] = explode('/', $id, 2);
        $classBase = $this->toStudly($name);
        $className = str_ends_with($classBase, 'Extension') ? $classBase : $classBase . 'Extension';
        $defaultPath = rtrim($basePath, '/') . '/src/' . $className . '.php';

        if (!file_exists($defaultPath)) {
            $warnings[] = "entrypoint is missing and no default class found at src/{$className}.php.";
        }

        return [$errors, $warnings];
    }

    private function readComposerEntrypoint(string $basePath): ?string
    {
        $composerPath = rtrim($basePath, '/') . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        $raw = file_get_contents($composerPath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $extra = $decoded['extra'] ?? null;
        if (!is_array($extra)) {
            return null;
        }

        $notur = $extra['notur'] ?? null;
        if (!is_array($notur)) {
            return null;
        }

        $entrypoint = $notur['entrypoint'] ?? null;
        return is_string($entrypoint) && $entrypoint !== '' ? $entrypoint : null;
    }

    private function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $intVal = (int) $value;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }

    private function parseDay(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0 && $value <= 6) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $map = [
                'sun' => 0,
                'sunday' => 0,
                'mon' => 1,
                'monday' => 1,
                'tue' => 2,
                'tues' => 2,
                'tuesday' => 2,
                'wed' => 3,
                'wednesday' => 3,
                'thu' => 4,
                'thursday' => 4,
                'fri' => 5,
                'friday' => 5,
                'sat' => 6,
                'saturday' => 6,
            ];

            return $map[$normalized] ?? null;
        }

        return null;
    }
}
