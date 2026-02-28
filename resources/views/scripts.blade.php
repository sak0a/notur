{{-- Notur Extension Framework - Injected into Pterodactyl panel layout --}}
@if(isset($noturConfig))
<script>
    window.__NOTUR__ = @json($noturConfig);
    window.__NOTUR__.registry = null; // Populated by bridge.js
    window.__NOTUR__.diagnostics = window.__NOTUR__.diagnostics || { errors: [] };
    window.__NOTUR__.diagnostics.bridgeScriptLoaded = false;
    window.__NOTUR__.diagnostics.bridgeLoadError = null;
</script>
@if(!request()->is('admin*'))
<script
    src="/notur/bridge.js"
    defer
    onload="
        window.__NOTUR__ = window.__NOTUR__ || {};
        window.__NOTUR__.diagnostics = window.__NOTUR__.diagnostics || { errors: [] };
        window.__NOTUR__.diagnostics.bridgeScriptLoaded = true;
        window.__NOTUR__.diagnostics.bridgeLoadedAt = (new Date()).toISOString();
    "
    onerror="
        window.__NOTUR__ = window.__NOTUR__ || {};
        window.__NOTUR__.diagnostics = window.__NOTUR__.diagnostics || { errors: [] };
        window.__NOTUR__.diagnostics.bridgeScriptLoaded = false;
        window.__NOTUR__.diagnostics.bridgeLoadError = 'Failed to load /notur/bridge.js';
        window.__NOTUR__.diagnostics.bridgeLoadFailedAt = (new Date()).toISOString();
    "
></script>
<link rel="stylesheet" href="/notur/tailwind.css">
@foreach($noturConfig['extensions'] ?? [] as $extension)
    @if(!empty($extension['styles']))
        <link rel="stylesheet" href="{{ $extension['styles'] }}">
    @endif
@endforeach
@foreach($noturConfig['extensions'] ?? [] as $extension)
    @if(!empty($extension['bundle']))
        <script src="{{ $extension['bundle'] }}" defer></script>
    @endif
@endforeach
@else
<script>
    window.__NOTUR__.diagnostics.bridgeLoadError = 'Bridge bootstrap skipped on admin routes.';
</script>
@endif
@endif
