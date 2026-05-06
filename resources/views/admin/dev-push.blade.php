@extends('layouts.admin')

@section('title', 'Notur — Developer Push')

@section('content-header')
    <h1>Notur — Developer Push <small>API keys & remote-pushed extensions</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ url('/admin') }}">Admin</a></li>
        <li><a href="{{ route('admin.notur.extensions') }}">Extensions</a></li>
        <li class="active">Developer Push</li>
    </ol>
@endsection

@section('content')
@include('notur::admin.partials.brutalist-styles')
<script>document.body.classList.add('notur-admin-page');</script>

<div class="row">
    <div class="col-md-12">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($newlyCreatedKey))
            <div class="alert alert-warning" style="border:2px solid #f39c12;">
                <h4 style="margin-top:0;">Copy this key now</h4>
                <p>This is the only time the full key for <strong>{{ $newlyCreatedKey['name'] }}</strong> will be shown.</p>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" readonly value="{{ $newlyCreatedKey['plaintext'] }}" id="notur-new-key" class="form-control" style="font-family:monospace;">
                    <button type="button" class="btn btn-primary" onclick="(function(){var i=document.getElementById('notur-new-key');i.select();document.execCommand('copy');})();">Copy</button>
                </div>
            </div>
        @endif

        {{-- Section 1: API keys --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">API keys</h3>
            </div>
            <div class="box-body">
                <form action="{{ route('admin.notur.dev-push.keys.create') }}" method="POST" class="form-inline" style="margin-bottom:16px;">
                    @csrf
                    <div class="form-group">
                        <label for="key-name" class="sr-only">Name</label>
                        <input type="text" id="key-name" name="name" class="form-control" placeholder="Key name (e.g. Alice's laptop)" required maxlength="120">
                    </div>
                    <button type="submit" class="btn btn-primary">Create key</button>
                </form>

                @if ($keys->isEmpty())
                    <p class="text-muted" style="margin:0;">No API keys yet. Create one above to enable remote pushes.</p>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Preview</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Last used</th>
                                <th style="width:220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($keys as $key)
                                <tr>
                                    <td>{{ $key->name }}</td>
                                    <td><code>{{ $key->prefix }}…</code></td>
                                    <td>
                                        @if ($key->isActive())
                                            <span class="label label-success">active</span>
                                        @else
                                            <span class="label label-default">revoked</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($key->created_at)->toDateTimeString() ?? '—' }}</td>
                                    <td>{{ optional($key->last_used_at)->toDateTimeString() ?? 'never' }}</td>
                                    <td>
                                        @if ($key->isActive())
                                            <form action="{{ route('admin.notur.dev-push.keys.regenerate', $key->id) }}" method="POST" class="inline" onsubmit="return confirm('Regenerate {{ $key->name }}? The current key will stop working immediately.');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-warning">Regenerate</button>
                                            </form>
                                            <form action="{{ route('admin.notur.dev-push.keys.revoke', $key->id) }}" method="POST" class="inline" onsubmit="return confirm('Revoke {{ $key->name }}? This cannot be undone.');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-danger">Revoke</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Section 2: Remote-pushed extensions --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Remote-pushed extensions</h3>
            </div>
            <div class="box-body">
                @if ($pushedExtensions->isEmpty())
                    <p class="text-muted" style="margin:0;">No remote-pushed extensions yet. See the developer help below for the local CLI workflow.</p>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Version</th>
                                <th>Source</th>
                                <th>Pushed via</th>
                                <th>Checksum</th>
                                <th>Last pushed</th>
                                <th>Status</th>
                                <th>Errors</th>
                                <th style="width:220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pushedExtensions as $ext)
                                <tr>
                                    <td><code>{{ $ext->extension_id }}</code></td>
                                    <td>{{ $ext->name }}</td>
                                    <td>{{ $ext->version }}</td>
                                    <td><span class="label label-info" style="font-family:monospace;">{{ $ext->source }}</span></td>
                                    <td>{{ optional($ext->pushedViaKey)->name ?? '—' }}</td>
                                    <td><code title="{{ $ext->package_checksum }}">{{ $ext->package_checksum ? substr($ext->package_checksum, 0, 12) : '—' }}</code></td>
                                    <td>{{ optional($ext->last_pushed_at)->toDateTimeString() ?? '—' }}</td>
                                    <td>
                                        @if ($ext->enabled)
                                            <span class="label label-success">enabled</span>
                                        @else
                                            <span class="label label-default">disabled</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($ext->last_push_error)
                                            <span title="{{ $ext->last_push_error }}" class="text-danger">{{ \Illuminate\Support\Str::limit($ext->last_push_error, 40) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.notur.extensions.show', $ext->extension_id) }}" class="btn btn-xs btn-default">Details</a>
                                        @if ($ext->enabled)
                                            <form action="{{ route('admin.notur.extensions.disable', $ext->extension_id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-warning">Disable</button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.notur.extensions.enable', $ext->extension_id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-success">Enable</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('admin.notur.extensions.remove', $ext->extension_id) }}" method="POST" class="inline" onsubmit="return confirm('Remove {{ $ext->extension_id }}? This deletes files and rolls back migrations.');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Section 3: Developer help --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Developer help</h3>
            </div>
            <div class="box-body">
                <details>
                    <summary style="cursor:pointer;">Show local <code>.env</code> + commands</summary>
                    <p style="margin-top:12px;">A developer pushing extensions from their machine sets these in their extension project's <code>.env</code>:</p>
                    <pre style="background:#0a0a0b;color:#eaeaea;padding:12px;">NOTUR_REMOTE_HOST=https://panel.example.com
NOTUR_REMOTE_KEY=notur_xxx</pre>

                    <p>Then runs:</p>
                    <pre style="background:#0a0a0b;color:#eaeaea;padding:12px;">npx notur-create
npm run validate
npm run pack
npm run push
npx notur-doctor</pre>

                    <h4>Security</h4>
                    <ul>
                        <li>Keys are used only for remote development push. Don't share them.</li>
                        <li>Revoke keys when a developer no longer needs access.</li>
                        <li>Use a separate key per developer / device — easier to rotate later.</li>
                        <li>Env-config keys (<code>NOTUR_REMOTE_PUSH_KEYS</code>) still work for backwards compatibility, but DB keys are tracked and revocable.</li>
                    </ul>
                </details>
            </div>
        </div>

    </div>
</div>
@endsection
