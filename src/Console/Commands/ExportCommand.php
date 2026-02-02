<?php

declare(strict_types=1);

namespace Notur\Console\Commands;

use Illuminate\Console\Command;
use Notur\ExtensionManifest;
use Notur\Support\NoturArchive;

class ExportCommand extends Command
{
    protected $signature = 'notur:export
        {path? : Path to the extension to export (defaults to current directory)}
        {--output= : Output file path}
        {--sign : Sign the archive with your secret key}';

    protected $description = 'Export an extension as a .notur archive';

    public function handle(): int
    {
        $path = realpath($this->argument('path') ?? getcwd());

        if (!$path || !is_dir($path)) {
            $this->error('Path does not exist or is not a directory.');
            return 1;
        }

        // Load and validate the extension manifest
        try {
            $manifest = ExtensionManifest::load($path);
        } catch (\Throwable $e) {
            $this->error("Invalid extension: {$e->getMessage()}");
            return 1;
        }

        $id = $manifest->getId();
        $version = $manifest->getVersion();
        $filename = str_replace('/', '-', $id) . "-{$version}.notur";
        $outputPath = $this->option('output') ?? getcwd() . '/' . $filename;

        $this->info("Exporting {$id} v{$version}...");

        // Create .notur archive using the NoturArchive utility
        try {
            $result = NoturArchive::pack($path, $outputPath);
        } catch (\Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");
            return 1;
        }

        $checksums = $result['checksums'];
        $fileCount = count($checksums);
        $this->info("Packed {$fileCount} file(s) with SHA-256 checksums.");

        // Write standalone checksum file for the archive itself
        $archiveChecksum = hash_file('sha256', $outputPath);
        file_put_contents(
            $outputPath . '.sha256',
            $archiveChecksum . '  ' . basename($outputPath) . "\n",
        );
        $this->info("Archive checksum: {$archiveChecksum}");

        // Sign if requested
        if ($this->option('sign')) {
            $secretKey = env('NOTUR_SECRET_KEY');
            if (!$secretKey) {
                $this->error('NOTUR_SECRET_KEY environment variable is not set.');
                return 1;
            }

            /** @var \Notur\Support\SignatureVerifier $verifier */
            $verifier = app(\Notur\Support\SignatureVerifier::class);
            $signature = $verifier->sign($outputPath, $secretKey);
            file_put_contents($outputPath . '.sig', $signature);
            $this->info('Archive signed.');
        }

        $this->info("Exported to: {$outputPath}");

        return 0;
    }
}
