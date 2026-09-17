<?php
// Disposable browser-test fixture: disabled v1 installed, v2 available locally.
require '/var/www/pterodactyl/vendor/autoload.php';
$app = require '/var/www/pterodactyl/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source = sys_get_temp_dir() . '/notur-update-fixture';
@mkdir($source, 0755, true);
foreach (['1.0.0', '2.0.0'] as $version) {
    file_put_contents($source . '/extension.yaml', "id: audit/ui-update\nname: UI Update Fixture\nversion: {$version}\n");
    \Notur\Support\NoturArchive::pack($source, "/tmp/ui-update-{$version}.notur");
}
if (\Illuminate\Support\Facades\Artisan::call('notur:add', ['extension' => '/tmp/ui-update-1.0.0.notur', '--force' => true]) !== 0) {
    throw new \RuntimeException(\Illuminate\Support\Facades\Artisan::output());
}
\Illuminate\Support\Facades\Artisan::call('notur:disable', ['extension' => 'audit/ui-update']);
\Notur\Models\ExtensionSetting::updateOrCreate(['extension_id' => 'audit/ui-update', 'key' => 'retained'], ['value' => 'keep me']);
$archive = public_path('notur/e2e-registry/ui-update.notur');
copy('/tmp/ui-update-2.0.0.notur', $archive);
$cachePath = storage_path('notur/registry-cache.json');
$cache = json_decode(file_get_contents($cachePath), true);
$cache['registry']['extensions'] = array_values(array_filter($cache['registry']['extensions'], fn ($ext) => $ext['id'] !== 'audit/ui-update'));
$cache['registry']['extensions'][] = ['id' => 'audit/ui-update', 'name' => 'UI Update Fixture', 'latest_version' => '2.0.0', 'archive_url' => 'http://127.0.0.1/notur/e2e-registry/ui-update.notur', 'sha256' => hash_file('sha256', $archive)];
file_put_contents($cachePath, json_encode($cache));
