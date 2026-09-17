#!/usr/bin/env bash
set -euo pipefail

case "${1:-}" in
    locked)
        composer install --no-interaction --prefer-dist --no-progress
        expected='12 10 11'
        ;;
    latest-laravel11)
        composer require --dev 'orchestra/testbench:^9.0' 'phpunit/phpunit:^11.0' --no-update --no-interaction
        composer config policy.advisories.block false
        composer update --with-all-dependencies --no-interaction --prefer-dist --no-progress
        expected='11 9 11'
        ;;
    lowest-laravel10|latest-laravel10)
        # Testbench 8 targets Laravel 10; Testbench 9 targets Laravel 11.
        # Keep this override local to the CI checkout. The committed lock stays
        # on Laravel 12 for ordinary installs and the security audit.
        composer require --dev 'orchestra/testbench:^8.0' 'phpunit/phpunit:^10.5' --no-update --no-interaction
        composer config minimum-stability stable
        # Laravel 10 packages have published advisories. These compatibility
        # lanes test the declared range; audit runs on the committed lock.
        composer config policy.advisories.block false
        if [[ "$1" == lowest-laravel10 ]]; then
            composer update --prefer-lowest --prefer-stable --with-all-dependencies --no-interaction --prefer-dist --no-progress
        else
            composer update --prefer-stable --with-all-dependencies --no-interaction --prefer-dist --no-progress
        fi
        expected='10 8 10'
        ;;
    *)
        echo "Usage: $0 {locked|lowest-laravel10|latest-laravel10|latest-laravel11}" >&2
        exit 2
        ;;
esac

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
