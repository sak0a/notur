@extends('layouts.admin')

@section('title')
    Notur Diagnostics
@endsection

@section('content-header')
    <h1>Notur Diagnostics<small>Frontend Runtime</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.notur.extensions') }}">Extensions</a></li>
        <li class="active">Diagnostics</li>
    </ol>
@endsection

@section('content')
    @include('notur::admin.partials.brutalist-styles')

    @php
        $framework = $generalInfo['framework'] ?? [];
        $runtime = $generalInfo['runtime'] ?? [];
        $hardware = $generalInfo['hardware'] ?? [];
        $packageManager = $generalInfo['package_manager'] ?? [];
        $noturUpdate = $generalInfo['updates']['notur'] ?? [];
        $updateStatus = $noturUpdate['status'] ?? 'unknown';
        $updateClass = $updateStatus === 'update_available'
            ? 'label label-warning'
            : ($updateStatus === 'up_to_date' ? 'label label-success' : 'label label-default');
    @endphp

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-info-circle" style="margin-right: 8px; opacity: 0.5;"></i>General System Info</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h4 style="margin-top: 0;">Framework</h4>
                            <table class="table table-condensed">
                                <tbody>
                                <tr><th>Notur</th><td>{{ $framework['notur'] ?? 'unknown' }}</td></tr>
                                <tr><th>Laravel</th><td>{{ $framework['laravel'] ?? 'unknown' }}</td></tr>
                                <tr><th>PHP</th><td>{{ $framework['php'] ?? 'unknown' }}</td></tr>
                                <tr><th>Panel</th><td>{{ $framework['panel'] ?? 'unknown' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h4 style="margin-top: 0;">Runtime / Hardware</h4>
                            <table class="table table-condensed">
                                <tbody>
                                <tr><th>Environment</th><td>{{ $runtime['environment'] ?? 'unknown' }}</td></tr>
                                <tr><th>Debug</th><td>{{ ($runtime['debug'] ?? false) ? 'true' : 'false' }}</td></tr>
                                <tr><th>Timezone</th><td>{{ $runtime['timezone'] ?? 'unknown' }}</td></tr>
                                <tr><th>OS</th><td>{{ $hardware['os'] ?? 'unknown' }} ({{ $hardware['architecture'] ?? 'unknown' }})</td></tr>
                                <tr><th>Kernel</th><td>{{ $hardware['kernel'] ?? 'unknown' }}</td></tr>
                                <tr><th>CPU Cores</th><td>{{ $hardware['cpu_cores'] ?? 'unknown' }}</td></tr>
                                <tr><th>Memory Limit</th><td>{{ $runtime['memory_limit'] ?? 'unknown' }}</td></tr>
                                <tr><th>Disk Free</th><td>{{ $hardware['disk_free'] ?? 'unknown' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h4 style="margin-top: 0;">Tooling / Updates</h4>
                            <table class="table table-condensed">
                                <tbody>
                                <tr>
                                    <th>Package Manager</th>
                                    <td>{{ $packageManager['manager'] ?? 'unknown' }}</td>
                                </tr>
                                <tr>
                                    <th>Detection</th>
                                    <td>{{ $packageManager['source'] ?? 'unknown' }}</td>
                                </tr>
                                <tr>
                                    <th>Lockfile</th>
                                    <td>{{ $packageManager['lockfile'] ?? 'none' }}</td>
                                </tr>
                                <tr>
                                    <th>CLI Available</th>
                                    <td>{{ ($packageManager['command_available'] ?? false) ? 'yes' : 'no' }}</td>
                                </tr>
                                <tr>
                                    <th>Notur Update</th>
                                    <td><span class="{{ $updateClass }}">{{ str_replace('_', ' ', $updateStatus) }}</span></td>
                                </tr>
                                <tr>
                                    <th>Current</th>
                                    <td>{{ $noturUpdate['current_version'] ?? 'unknown' }}</td>
                                </tr>
                                <tr>
                                    <th>Latest</th>
                                    <td>{{ $noturUpdate['latest_version'] ?? 'unknown' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-terminal" style="margin-right: 8px; opacity: 0.5;"></i>Runtime Snapshot</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        This page captures a live snapshot of the Notur frontend runtime.
                        It requires the bridge and extension bundles to load on this page.
                    </p>
                    <pre id="notur-diagnostics-json" style="max-height: 520px; overflow: auto; background: var(--nb-void, #0a0a0b); color: var(--nb-accent-light, #a78bfa); padding: 16px; border: 1px solid var(--nb-border, #222); font-family: var(--nb-mono, monospace); font-size: 12px; line-height: 1.6;">Loading...</pre>
                </div>
            </div>
        </div>
    </div>

    {{-- Notur Brand --}}
    <div class="nb-brand-bar">
        <div class="nb-brand-bar__logo">N</div>
        <div class="nb-brand-bar__text">Notur Extension Framework</div>
    </div>

    @include('notur::scripts')

    <script>
        (function () {
            const target = document.getElementById('notur-diagnostics-json');
            if (!target) {
                return;
            }

            const RETRY_DELAY_MS = 250;
            const MAX_WAIT_MS = 15000;
            const startedAt = Date.now();

            function observedState() {
                const notur = window.__NOTUR__;
                return {
                    hasNotur: !!notur,
                    hasRegistry: !!(notur && notur.registry),
                    bridgeScriptLoaded: !!(notur && notur.diagnostics && notur.diagnostics.bridgeScriptLoaded),
                    bridgeLoadError: notur && notur.diagnostics ? (notur.diagnostics.bridgeLoadError || null) : null,
                    bridgeLoadedAt: notur && notur.diagnostics ? (notur.diagnostics.bridgeLoadedAt || null) : null,
                    bridgeLoadFailedAt: notur && notur.diagnostics ? (notur.diagnostics.bridgeLoadFailedAt || null) : null,
                };
            }

            function snapshot() {
                const notur = window.__NOTUR__;
                if (!notur || !notur.registry) {
                    return null;
                }

                const slots = {};
                if (notur.SLOT_DEFINITIONS && Array.isArray(notur.SLOT_DEFINITIONS)) {
                    notur.SLOT_DEFINITIONS.forEach(def => {
                        if (!def || !def.id || typeof notur.registry.getSlot !== 'function') {
                            return;
                        }
                        slots[def.id] = notur.registry.getSlot(def.id) || [];
                    });
                }

                const routes = {
                    server: typeof notur.registry.getRoutes === 'function' ? notur.registry.getRoutes('server') : [],
                    dashboard: typeof notur.registry.getRoutes === 'function' ? notur.registry.getRoutes('dashboard') : [],
                    account: typeof notur.registry.getRoutes === 'function' ? notur.registry.getRoutes('account') : [],
                };

                return {
                    status: 'ok',
                    collected_at: (new Date()).toISOString(),
                    observed: observedState(),
                    runtime: {
                        version: notur.version,
                        extensions: notur.extensions || [],
                        slots,
                        routes,
                        errors: notur.diagnostics?.errors || [],
                    },
                };
            }

            function renderJson(data) {
                target.textContent = JSON.stringify(data, null, 2);
            }

            function render() {
                const data = snapshot();
                if (!data) {
                    const elapsedMs = Date.now() - startedAt;
                    const state = observedState();

                    if (state.bridgeLoadError) {
                        renderJson({
                            status: 'error',
                            reason: 'bridge_script_load_failed',
                            elapsed_ms: elapsedMs,
                            observed: state,
                            hints: [
                                'Ensure /notur/bridge.js exists and is readable by the web server.',
                                'Run notur dev/pull build steps to regenerate bridge assets if missing.',
                            ],
                        });
                        return;
                    }

                    if (elapsedMs >= MAX_WAIT_MS) {
                        renderJson({
                            status: 'error',
                            reason: 'registry_initialization_timeout',
                            elapsed_ms: elapsedMs,
                            observed: state,
                            hints: [
                                'The bridge loaded but did not initialize registry in time.',
                                'Check browser console for bridge/runtime errors in extension bundles.',
                            ],
                        });
                        return;
                    }

                    renderJson({
                        status: 'loading',
                        elapsed_ms: elapsedMs,
                        observed: state,
                    });
                    setTimeout(render, RETRY_DELAY_MS);
                    return;
                }

                renderJson(data);
            }

            render();
        })();
    </script>
@endsection
