<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Notur\Support\SchemaValidator;

$registryPath = $argv[1] ?? dirname(__DIR__, 2) . '/registry/registry.json';

if (!is_file($registryPath)) {
    fwrite(STDERR, "ERROR: Registry file not found: {$registryPath}\n");
    exit(1);
}

$raw = file_get_contents($registryPath);
if (!is_string($raw) || $raw === '') {
    fwrite(STDERR, "ERROR: Failed to read registry file: {$registryPath}\n");
    exit(1);
}

$index = json_decode($raw, true);
if (!is_array($index)) {
    fwrite(STDERR, "ERROR: Invalid JSON in {$registryPath}\n");
    exit(1);
}

$errors = SchemaValidator::validateRegistryIndex($index);

$extensions = $index['extensions'] ?? null;
if (!is_array($extensions)) {
    $errors[] = '$.extensions: expected array';
    $extensions = [];
}

$ids = [];
foreach ($extensions as $i => $ext) {
    $path = '$.extensions[' . $i . ']';
    if (!is_array($ext)) {
        $errors[] = "{$path}: expected object";
        continue;
    }

    $id = $ext['id'] ?? null;
    if (!is_string($id) || $id === '') {
        $errors[] = "{$path}.id: missing or invalid";
    } elseif (isset($ids[$id])) {
        $errors[] = "{$path}.id: duplicate extension id '{$id}'";
    } else {
        $ids[$id] = true;
    }

    $repository = $ext['repository'] ?? null;
    if (!is_string($repository) || filter_var($repository, FILTER_VALIDATE_URL) === false) {
        $errors[] = "{$path}.repository: invalid URL";
    }

    $latestVersion = $ext['latest_version'] ?? $ext['version'] ?? null;
    $versions = $ext['versions'] ?? null;
    if (is_string($latestVersion) && is_array($versions) && $versions !== [] && !in_array($latestVersion, $versions, true)) {
        $errors[] = "{$path}: latest_version '{$latestVersion}' is not present in versions[]";
    }

    $sha256 = $ext['sha256'] ?? null;
    if (is_string($sha256) && !preg_match('/^[a-f0-9]{64}$/i', trim($sha256))) {
        $errors[] = "{$path}.sha256: expected 64-char hex string";
    }
    if (is_array($sha256)) {
        foreach ($sha256 as $version => $checksum) {
            if (!is_string($version) || !is_string($checksum) || !preg_match('/^[a-f0-9]{64}$/i', trim($checksum))) {
                $errors[] = "{$path}.sha256: invalid checksum entry for version '{$version}'";
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Registry validation failed with " . count($errors) . " error(s):\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Registry validation passed for " . count($extensions) . " extension(s).\n");
exit(0);
