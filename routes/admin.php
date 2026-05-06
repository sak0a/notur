<?php

use Illuminate\Support\Facades\Route;
use Notur\Http\Controllers\ExtensionAdminController;

$adminMiddleware = class_exists(\Pterodactyl\Http\Middleware\AdminAuthenticate::class)
    ? \Pterodactyl\Http\Middleware\AdminAuthenticate::class
    : 'admin';

Route::prefix('admin/notur')
    ->middleware(['web', $adminMiddleware])
    ->group(function () {
        Route::get('/extensions', [ExtensionAdminController::class, 'index'])
            ->name('admin.notur.extensions');

        Route::get('/slots', [ExtensionAdminController::class, 'slots'])
            ->name('admin.notur.slots');

        Route::get('/diagnostics', [ExtensionAdminController::class, 'diagnostics'])
            ->name('admin.notur.diagnostics');

        Route::post('/diagnostics/notur/update', [ExtensionAdminController::class, 'updateNotur'])
            ->name('admin.notur.diagnostics.update-notur');

        Route::get('/health', [ExtensionAdminController::class, 'health'])
            ->name('admin.notur.health');

        Route::get('/extensions/{extensionId}', [ExtensionAdminController::class, 'show'])
            ->name('admin.notur.extensions.show')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::post('/extensions/{extensionId}/settings', [ExtensionAdminController::class, 'updateSettings'])
            ->name('admin.notur.extensions.settings')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::get('/extensions/{extensionId}/settings/preview', [ExtensionAdminController::class, 'settingsPreview'])
            ->name('admin.notur.extensions.settings.preview')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::post('/extensions/install', [ExtensionAdminController::class, 'install'])
            ->name('admin.notur.extensions.install');

        Route::post('/extensions/registry/sync', [ExtensionAdminController::class, 'syncRegistry'])
            ->name('admin.notur.extensions.registry.sync');

        Route::post('/extensions/update-all', [ExtensionAdminController::class, 'updateAll'])
            ->name('admin.notur.extensions.update-all');

        Route::post('/extensions/{extensionId}/update', [ExtensionAdminController::class, 'update'])
            ->name('admin.notur.extensions.update')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::post('/extensions/{extensionId}/enable', [ExtensionAdminController::class, 'enable'])
            ->name('admin.notur.extensions.enable')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::post('/extensions/{extensionId}/disable', [ExtensionAdminController::class, 'disable'])
            ->name('admin.notur.extensions.disable')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::post('/extensions/{extensionId}/remove', [ExtensionAdminController::class, 'remove'])
            ->name('admin.notur.extensions.remove')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');

        Route::get('/dev-push', [\Notur\Http\Controllers\RemotePushAdminController::class, 'index'])
            ->name('admin.notur.dev-push');

        Route::post('/dev-push/keys', [\Notur\Http\Controllers\RemotePushAdminController::class, 'createKey'])
            ->name('admin.notur.dev-push.keys.create');

        Route::post('/dev-push/keys/{id}/revoke', [\Notur\Http\Controllers\RemotePushAdminController::class, 'revokeKey'])
            ->name('admin.notur.dev-push.keys.revoke')
            ->where('id', '[0-9]+');

        Route::post('/dev-push/keys/{id}/regenerate', [\Notur\Http\Controllers\RemotePushAdminController::class, 'regenerateKey'])
            ->name('admin.notur.dev-push.keys.regenerate')
            ->where('id', '[0-9]+');

        Route::get('/dev-push/extensions/{extensionId}/manifest', [\Notur\Http\Controllers\RemotePushAdminController::class, 'showManifest'])
            ->name('admin.notur.dev-push.manifest')
            ->where('extensionId', '[a-z0-9\-]+/[a-z0-9\-]+');
    });
