<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Notur\Models\InstalledExtension;
use Pterodactyl\Models\Setting;
use Pterodactyl\Models\User;
use Symfony\Component\Yaml\Yaml;

require '/var/www/pterodactyl/vendor/autoload.php';

$app = require '/var/www/pterodactyl/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$adminEmail = getenv('E2E_ADMIN_EMAIL') ?: 'admin@example.com';
$adminUsername = getenv('E2E_ADMIN_USERNAME') ?: 'admin';
$adminPassword = getenv('E2E_ADMIN_PASSWORD') ?: 'notur-admin-password';
$userEmail = getenv('E2E_USER_EMAIL') ?: 'user@example.com';
$userUsername = getenv('E2E_USER_USERNAME') ?: 'user';
$userPassword = getenv('E2E_USER_PASSWORD') ?: 'notur-user-password';

$helloWorldPath = '/var/www/pterodactyl/notur/extensions/notur/hello-world';
$fullExtensionPath = '/var/www/pterodactyl/notur/extensions/notur/full-extension';
$manifestFile = '/var/www/pterodactyl/notur/extensions.json';

$helloWorldManifest = Yaml::parseFile($helloWorldPath . '/extension.yaml');
$fullExtensionManifest = Yaml::parseFile($fullExtensionPath . '/extension.yaml');
$fullExtensionManifest['admin']['settings'] = [
    'title' => 'E2E Extension Settings',
    'description' => 'Settings seeded for browser E2E coverage.',
    'fields' => [
        [
            'key' => 'feature_enabled',
            'label' => 'Feature Enabled',
            'type' => 'boolean',
            'default' => true,
            'help' => 'Controls whether the example feature is active.',
        ],
        [
            'key' => 'request_limit',
            'label' => 'Request Limit',
            'type' => 'number',
            'required' => true,
            'default' => 5,
            'help' => 'Required numeric setting for validation coverage.',
        ],
    ],
];
$fullExtensionManifest['health']['checks'] = [
    [
        'id' => 'configuration',
        'label' => 'Configuration',
        'severity' => 'normal',
        'description' => 'E2E health check definition rendered from the manifest.',
    ],
];

$admin = User::query()->where('email', $adminEmail)->first();
if (!$admin instanceof User) {
    $admin = new User();
    $admin->uuid = (string) Str::uuid();
    $admin->email = $adminEmail;
}

$admin->username = $adminUsername;
$admin->name_first = 'E2E';
$admin->name_last = 'Admin';
$admin->password = Hash::make($adminPassword);
$admin->root_admin = true;
$admin->use_totp = false;
$admin->totp_secret = null;
$admin->totp_authenticated_at = null;
$admin->language = 'en';
$admin->gravatar = true;
$admin->save();

$user = User::query()->where('email', $userEmail)->first();
if (!$user instanceof User) {
    $user = new User();
    $user->uuid = (string) Str::uuid();
    $user->email = $userEmail;
}

$user->username = $userUsername;
$user->name_first = 'E2E';
$user->name_last = 'User';
$user->password = Hash::make($userPassword);
$user->root_admin = false;
$user->use_totp = false;
$user->totp_secret = null;
$user->totp_authenticated_at = null;
$user->language = 'en';
$user->gravatar = true;
$user->save();

Setting::query()->updateOrCreate(
    ['key' => 'settings::recaptcha:enabled'],
    ['value' => 'false'],
);

$upsertExtension = static function (string $id, string $name, array $manifest, bool $enabled): InstalledExtension {
    /** @var InstalledExtension $extension */
    $extension = InstalledExtension::query()->updateOrCreate(
        ['extension_id' => $id],
        [
            'name' => $name,
            'version' => (string) ($manifest['version'] ?? '1.0.0'),
            'enabled' => $enabled,
            'manifest' => $manifest,
        ],
    );

    return $extension;
};

$helloWorld = $upsertExtension(
    'notur/hello-world',
    (string) ($helloWorldManifest['name'] ?? 'Hello World'),
    $helloWorldManifest,
    true,
);

$fullExtension = $upsertExtension(
    'notur/full-extension',
    (string) ($fullExtensionManifest['name'] ?? 'Notur Full Example'),
    $fullExtensionManifest,
    false,
);

$upsertExtension(
    'broken/remove-fail',
    'Broken Remove Fixture',
    [
        'id' => 'broken/remove-fail',
        'name' => 'Broken Remove Fixture',
        'version' => '0.0.1',
        'description' => 'Intentionally inconsistent extension record for negative E2E coverage.',
    ],
    false,
);

$extensionsManifest = [
    'extensions' => [
        'notur/hello-world' => [
            'enabled' => true,
            'version' => $helloWorld->version,
            'installed_at' => optional($helloWorld->created_at)->toIso8601String() ?? now()->toIso8601String(),
        ],
        'notur/full-extension' => [
            'enabled' => false,
            'version' => $fullExtension->version,
            'installed_at' => optional($fullExtension->created_at)->toIso8601String() ?? now()->toIso8601String(),
        ],
    ],
];

file_put_contents(
    $manifestFile,
    json_encode($extensionsManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
);
