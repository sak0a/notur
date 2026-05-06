<?php

declare(strict_types=1);

namespace Notur\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Notur\Models\InstalledExtension;
use Notur\Models\RemotePushApiKey;

class RemotePushAdminController extends Controller
{
    public function index(Request $request): Renderable
    {
        $this->guardAdmin($request);

        $keys = RemotePushApiKey::query()
            ->orderByRaw('revoked_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->get();

        $pushedExtensions = InstalledExtension::query()
            ->where('source', 'remote_push')
            ->orderByDesc('last_pushed_at')
            ->orderBy('extension_id')
            ->with('pushedViaKey')
            ->get();

        $newlyCreatedKey = $request->session()->get('newly_created_key');

        return view('notur::admin.dev-push', [
            'keys' => $keys,
            'pushedExtensions' => $pushedExtensions,
            'newlyCreatedKey' => $newlyCreatedKey,
        ]);
    }

    public function createKey(Request $request): RedirectResponse
    {
        $this->guardAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        $plaintext = RemotePushApiKey::generatePlaintext();

        RemotePushApiKey::create([
            'name' => $data['name'],
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
            'created_by_user_id' => optional($request->user())->getAuthIdentifier(),
        ]);

        return redirect()
            ->route('admin.notur.dev-push')
            ->with('newly_created_key', [
                'name' => $data['name'],
                'plaintext' => $plaintext,
            ])
            ->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function revokeKey(Request $request, int $id): RedirectResponse
    {
        $this->guardAdmin($request);

        $key = RemotePushApiKey::query()->findOrFail($id);
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return redirect()
            ->route('admin.notur.dev-push')
            ->with('success', "Key '{$key->name}' revoked.");
    }

    public function regenerateKey(Request $request, int $id): RedirectResponse
    {
        $this->guardAdmin($request);

        $original = RemotePushApiKey::query()->findOrFail($id);
        if ($original->revoked_at === null) {
            $original->forceFill(['revoked_at' => now()])->save();
        }

        $plaintext = RemotePushApiKey::generatePlaintext();

        RemotePushApiKey::create([
            'name' => $original->name,
            'prefix' => RemotePushApiKey::prefixOf($plaintext),
            'token_hash' => RemotePushApiKey::hashToken($plaintext),
            'created_by_user_id' => optional($request->user())->getAuthIdentifier(),
        ]);

        return redirect()
            ->route('admin.notur.dev-push')
            ->with('newly_created_key', [
                'name' => $original->name,
                'plaintext' => $plaintext,
            ])
            ->with('success', "Key '{$original->name}' regenerated. Copy it now.");
    }

    public function showManifest(Request $request, string $extensionId): JsonResponse
    {
        $this->guardAdmin($request);

        $row = InstalledExtension::query()->where('extension_id', $extensionId)->firstOrFail();

        return response()->json($row->manifest ?? new \stdClass());
    }

    private function guardAdmin(Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $isAdmin = false;
        if (property_exists($user, 'root_admin')) {
            $isAdmin = (bool) $user->root_admin;
        } elseif (method_exists($user, 'isRootAdmin')) {
            $isAdmin = (bool) $user->isRootAdmin();
        } elseif ($user instanceof \Illuminate\Database\Eloquent\Model) {
            $isAdmin = (bool) $user->getAttribute('root_admin');
        }

        if (!$isAdmin) {
            abort(403);
        }
    }
}
