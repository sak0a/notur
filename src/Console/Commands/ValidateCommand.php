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

        if (!empty($errors)) {
            $this->error('Validation failed:');
            foreach ($errors as $error) {
                $this->line(' - ' . $error);
            }
            return 1;
        }

        $this->info('Manifest is valid.');

        if (!empty($settingsWarnings)) {
            $this->warn('Settings schema warnings:');
            foreach ($settingsWarnings as $warning) {
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
}
