#!/usr/bin/env bash
set -euo pipefail

# CI tests the committed Laravel 12 / Testbench 10 dependency set.
# Keep the version assertion below to catch accidental lockfile drift.
composer install --no-interaction --prefer-dist --no-progress
expected='12 10 11'

php -r '
require "vendor/autoload.php";
[$laravel, $testbench, $phpunit] = array_map("intval", explode(" ", $argv[1]));
foreach (["laravel/framework" => $laravel, "orchestra/testbench" => $testbench, "phpunit/phpunit" => $phpunit] as $package => $major) {
    $version = Composer\InstalledVersions::getVersion($package);
    if ($version === null || (int) $version !== $major) {
        fwrite(STDERR, "$package: expected major $major, got " . ($version ?? "missing") . "\n");
        exit(1);
    }
    echo "$package $version\n";
}
' "$expected"
