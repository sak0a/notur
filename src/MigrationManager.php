<?php

declare(strict_types=1);

namespace Notur;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Notur\Models\ExtensionMigration;

class MigrationManager
{
    /**
     * Run pending migrations for an extension.
     */
    public function migrate(string $extensionId, string $migrationsPath): array
    {
        $ran = [];

        if (!is_dir($migrationsPath)) {
            return $ran;
        }

        $files = $this->getMigrationFiles($migrationsPath);
        $executed = $this->getExecutedMigrations($extensionId);

        foreach ($files as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (in_array($migrationName, $executed, true)) {
                continue;
            }

            require_once $file;

            $class = $this->resolveMigrationClass($migrationName);
            $instance = new $class();

            DB::transaction(function () use ($instance) {
                $instance->up();
            });

            ExtensionMigration::create([
                'extension_id' => $extensionId,
                'migration' => $migrationName,
                'batch' => $this->getNextBatch($extensionId),
            ]);

            $ran[] = $migrationName;
        }

        return $ran;
    }

    /**
     * Roll back all migrations for an extension.
     */
    public function rollback(string $extensionId, string $migrationsPath): array
    {
        $rolledBack = [];
        $executed = ExtensionMigration::where('extension_id', $extensionId)
            ->orderByDesc('batch')
            ->orderByDesc('id')
            ->get();

        foreach ($executed as $record) {
            $file = $migrationsPath . '/' . $record->migration . '.php';

            if (file_exists($file)) {
                require_once $file;

                $class = $this->resolveMigrationClass($record->migration);
                $instance = new $class();

                DB::transaction(function () use ($instance) {
                    $instance->down();
                });
            }

            $record->delete();
            $rolledBack[] = $record->migration;
        }

        return $rolledBack;
    }

    /**
     * Get the status of migrations for an extension.
     */
    public function status(string $extensionId, string $migrationsPath): array
    {
        $files = is_dir($migrationsPath) ? $this->getMigrationFiles($migrationsPath) : [];
        $executed = $this->getExecutedMigrations($extensionId);
        $status = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $status[] = [
                'migration' => $name,
                'ran' => in_array($name, $executed, true),
            ];
        }

        return $status;
    }

    private function getMigrationFiles(string $path): array
    {
        $files = glob($path . '/*.php');
        sort($files);
        return $files;
    }

    private function getExecutedMigrations(string $extensionId): array
    {
        if (!Schema::hasTable('notur_migrations')) {
            return [];
        }

        return ExtensionMigration::where('extension_id', $extensionId)
            ->pluck('migration')
            ->toArray();
    }

    private function getNextBatch(string $extensionId): int
    {
        $max = ExtensionMigration::where('extension_id', $extensionId)->max('batch');
        return ($max ?? 0) + 1;
    }

    private function resolveMigrationClass(string $migrationName): string
    {
        // Remove date prefix and convert to StudlyCase
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migrationName);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}
