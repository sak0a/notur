{{-- Notur Extension Framework - Injected into Pterodactyl panel layout --}}
@if(isset($noturConfig))
<script>
    window.__NOTUR__ = @json($noturConfig);
    window.__NOTUR__.registry = null; // Populated by bridge.js
</script>
<script src="/notur/bridge.js" defer></script>
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
@endif
