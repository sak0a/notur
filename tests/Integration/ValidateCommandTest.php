<?php

declare(strict_types=1);

namespace Notur\Tests\Integration;

use Notur\NoturServiceProvider;
use Orchestra\Testbench\TestCase;

class ValidateCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NoturServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_validate_command_accepts_valid_settings_schema(): void
    {
        $path = $this->createTempExtension([
            'admin' => [
                'settings' => [
                    'fields' => [
                        [
                            'key' => 'mode',
                            'type' => 'select',
                            'options' => [
                                ['value' => 'fast', 'label' => 'Fast'],
                                ['value' => 'safe', 'label' => 'Safe'],
                            ],
                            'default' => 'fast',
                        ],
                    ],
                ],
            ],
        ]);

        $this->artisan('notur:validate', ['path' => $path])
            ->assertExitCode(0);
    }

    public function test_validate_command_rejects_invalid_settings_schema(): void
    {
        $path = $this->createTempExtension([
            'admin' => [
                'settings' => [
                    'fields' => [
                        [
                            'key' => 'mode',
                            'type' => 'select',
                            'options' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $this->artisan('notur:validate', ['path' => $path])
            ->assertExitCode(1);
    }

    private function createTempExtension(array $override): string
    {
        $base = sys_get_temp_dir() . '/notur-test-' . uniqid();
        mkdir($base, 0775, true);

        $manifest = array_merge([
            'id' => 'acme/test',
            'name' => 'Test Extension',
            'version' => '1.0.0',
            'entrypoint' => 'Acme\\Test\\TestExtension',
        ], $override);

        $yaml = $this->toYaml($manifest);
        file_put_contents($base . '/extension.yaml', $yaml);

        return $base;
    }

    private function toYaml(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $lines = array_merge($lines, $this->yamlLine($key, $value, 0));
        }

        return implode("\n", $lines) . "\n";
    }

    private function yamlLine(string $key, mixed $value, int $indent): array
    {
        $prefix = str_repeat('  ', $indent);

        if (is_array($value)) {
            $lines = [$prefix . $key . ':'];
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $lines[] = $prefix . '  -';
                        foreach ($item as $itemKey => $itemValue) {
                            $lines = array_merge($lines, $this->yamlLine((string) $itemKey, $itemValue, $indent + 2));
                        }
                    } else {
                        $lines[] = $prefix . '  - ' . $this->yamlScalar($item);
                    }
                }
            } else {
                foreach ($value as $childKey => $childValue) {
                    $lines = array_merge($lines, $this->yamlLine((string) $childKey, $childValue, $indent + 1));
                }
            }
            return $lines;
        }

        return [$prefix . $key . ': ' . $this->yamlScalar($value)];
    }

    private function yamlScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
        return '"' . $escaped . '"';
    }
}
