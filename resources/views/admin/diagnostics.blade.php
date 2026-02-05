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
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Runtime Snapshot</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        This page captures a live snapshot of the Notur frontend runtime.
                        It requires the bridge and extension bundles to load on this page.
                    </p>
                    <pre id="notur-diagnostics-json" class="max-h-[520px] overflow-auto bg-[#111] text-slate-200 p-3 rounded">Loading…</pre>
                </div>
            </div>
        </div>
    </div>

    @include('notur::scripts')

    <script>
        (function () {
            const target = document.getElementById('notur-diagnostics-json');

            function snapshot() {
                const notur = window.__NOTUR__;
                if (!notur || !notur.registry) {
                    return null;
                }

                const slots = {};
                if (notur.SLOT_DEFINITIONS && Array.isArray(notur.SLOT_DEFINITIONS)) {
                    notur.SLOT_DEFINITIONS.forEach(def => {
                        slots[def.id] = notur.registry.getSlot(def.id) || [];
                    });
                }

                const routes = {
                    server: notur.registry.getRoutes('server'),
                    dashboard: notur.registry.getRoutes('dashboard'),
                    account: notur.registry.getRoutes('account'),
                };

                return {
                    version: notur.version,
                    extensions: notur.extensions || [],
                    slots,
                    routes,
                    errors: notur.diagnostics?.errors || [],
                };
            }

            function render() {
                const data = snapshot();
                if (!data) {
                    setTimeout(render, 250);
                    return;
                }
                target.textContent = JSON.stringify(data, null, 2);
            }

            render();
        })();
    </script>
@endsection
