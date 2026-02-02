<?php

declare(strict_types=1);

/**
 * Notur Registry Index Builder
 *
 * Generates a registry.json index from:
 * 1. A local directory of extension repositories (vendor/name layout), OR
 * 2. A JSON configuration file listing GitHub repos to fetch from.
 *
 * Usage:
 *   php build-index.php /path/to/extensions                   # Scan local directory
 *   php build-index.php --config repos.json                   # Fetch from GitHub repos
 *   php build-index.php /path/to/extensions --output registry.json
 *   php build-index.php --config repos.json --output registry.json
 *
 * Config file format (repos.json):
 *   {
 *     "repositories": [
 *       "notur/hello-world",
 *       "acme/server-stats"
 *     ]
 *   }
 */

// ---------------------------------------------------------------------------
// CLI argument parsing
// ---------------------------------------------------------------------------

$options = getopt('', ['config:', 'output:', 'help']);

if (isset($options['help']) || ($argc < 2 && !isset($options['config']))) {
    echo <<<USAGE
    Notur Registry Index Builder

    Usage:
      php build-index.php <extensions-dir> [--output <file>]
      php build-index.php --config <repos.json> [--output <file>]

    Options:
      <extensions-dir>    Local directory with vendor/name subdirectories
      --config <file>     JSON file listing GitHub repositories
      --output <file>     Write output to file instead of stdout
      --help              Show this help message

    USAGE;
    exit(isset($options['help']) ? 0 : 1);
}

$outputFile = $options['output'] ?? null;
$configFile = $options['config'] ?? null;

// ---------------------------------------------------------------------------
// Build the registry
// ---------------------------------------------------------------------------

$registry = [
    'version' => '1.0',
    'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'extensions' => [],
];

if ($configFile !== null) {
    // Mode: Fetch from GitHub repositories
    $registry['extensions'] = buildFromGitHub($configFile);
} else {
    // Mode: Scan local directory
    $extensionsDir = realpath($argv[1]);

    if (!$extensionsDir || !is_dir($extensionsDir)) {
        fwrite(STDERR, "Error: Directory not found: {$argv[1]}\n");
        exit(1);
    }

    $registry['extensions'] = buildFromLocalDirectory($extensionsDir);
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

$json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($outputFile !== null) {
    $dir = dirname($outputFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($outputFile, $json);
    fwrite(STDERR, "Registry written to: {$outputFile}\n");
    fwrite(STDERR, count($registry['extensions']) . " extension(s) indexed.\n");
} else {
    echo $json;
}

exit(0);

// ---------------------------------------------------------------------------
// Functions
// ---------------------------------------------------------------------------

/**
 * Build extension entries by scanning a local directory tree.
 *
 * Expected layout:
 *   extensions-dir/
 *     vendor1/
 *       extension-a/
 *         extension.yaml
 *       extension-b/
 *         extension.yml
 *     vendor2/
 *       ...
 */
function buildFromLocalDirectory(string $extensionsDir): array
{
    $extensions = [];

    $vendors = scandir($extensionsDir);
    foreach ($vendors as $vendor) {
        if ($vendor[0] === '.') {
            continue;
        }

        $vendorPath = $extensionsDir . '/' . $vendor;
        if (!is_dir($vendorPath)) {
            continue;
        }

        $extDirs = scandir($vendorPath);
        foreach ($extDirs as $ext) {
            if ($ext[0] === '.') {
                continue;
            }

            $extPath = $vendorPath . '/' . $ext;
            $manifest = loadManifestFromDir($extPath);

            if ($manifest === null) {
                fwrite(STDERR, "Warning: No manifest found in {$vendor}/{$ext}, skipping.\n");
                continue;
            }

            $extensions[] = buildEntry($manifest, "https://github.com/{$vendor}/{$ext}");
        }
    }

    return $extensions;
}

/**
 * Build extension entries by fetching manifests from GitHub repositories.
 */
function buildFromGitHub(string $configFile): array
{
    if (!file_exists($configFile)) {
        fwrite(STDERR, "Error: Config file not found: {$configFile}\n");
        exit(1);
    }

    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config) || !isset($config['repositories'])) {
        fwrite(STDERR, "Error: Config file must contain a 'repositories' array.\n");
        exit(1);
    }

    $extensions = [];

    foreach ($config['repositories'] as $repo) {
        $repo = trim($repo);
        if (empty($repo)) {
            continue;
        }

        fwrite(STDERR, "Fetching manifest for {$repo}...\n");

        $manifest = fetchManifestFromGitHub($repo);
        if ($manifest === null) {
            fwrite(STDERR, "Warning: Could not fetch manifest for {$repo}, skipping.\n");
            continue;
        }

        $extensions[] = buildEntry($manifest, "https://github.com/{$repo}");
    }

    return $extensions;
}

/**
 * Load a YAML manifest from a local directory.
 */
function loadManifestFromDir(string $extPath): ?array
{
    foreach (['extension.yaml', 'extension.yml'] as $filename) {
        $manifestFile = $extPath . '/' . $filename;

        if (!file_exists($manifestFile)) {
            continue;
        }

        // Use Symfony YAML if available, fall back to yaml_parse_file
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                return \Symfony\Component\Yaml\Yaml::parseFile($manifestFile);
            } catch (\Throwable $e) {
                fwrite(STDERR, "Warning: Failed to parse {$manifestFile}: {$e->getMessage()}\n");
                return null;
            }
        }

        if (function_exists('yaml_parse_file')) {
            $result = yaml_parse_file($manifestFile);
            return is_array($result) ? $result : null;
        }

        fwrite(STDERR, "Error: No YAML parser available. Install symfony/yaml.\n");
        exit(1);
    }

    return null;
}

/**
 * Fetch an extension manifest from a GitHub repository's default branch.
 */
function fetchManifestFromGitHub(string $repo): ?array
{
    $branches = ['main', 'master'];

    foreach ($branches as $branch) {
        foreach (['extension.yaml', 'extension.yml'] as $filename) {
            $url = "https://raw.githubusercontent.com/{$repo}/{$branch}/{$filename}";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Notur-IndexBuilder/1.0',
                    'ignore_errors' => true,
                ],
            ]);

            $content = @file_get_contents($url, false, $context);
            if ($content === false || str_contains($content, '404')) {
                continue;
            }

            if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                try {
                    return \Symfony\Component\Yaml\Yaml::parse($content);
                } catch (\Throwable) {
                    continue;
                }
            }

            if (function_exists('yaml_parse')) {
                $result = yaml_parse($content);
                return is_array($result) ? $result : null;
            }

            fwrite(STDERR, "Error: No YAML parser available. Install symfony/yaml.\n");
            exit(1);
        }
    }

    return null;
}

/**
 * Build a registry entry from manifest data.
 */
function buildEntry(array $manifest, string $repository): array
{
    $entry = [
        'id' => $manifest['id'] ?? 'unknown/unknown',
        'name' => $manifest['name'] ?? '',
        'description' => $manifest['description'] ?? '',
        'latest_version' => $manifest['version'] ?? '0.0.0',
        'versions' => [$manifest['version'] ?? '0.0.0'],
        'license' => $manifest['license'] ?? '',
        'authors' => $manifest['authors'] ?? [],
        'requires' => $manifest['requires'] ?? [],
        'repository' => $repository,
    ];

    if (isset($manifest['dependencies'])) {
        $entry['dependencies'] = $manifest['dependencies'];
    }

    if (isset($manifest['tags'])) {
        $entry['tags'] = $manifest['tags'];
    }

    return $entry;
}
