<form action="{{ route('admin.notur.extensions.remove', $extension->extension_id) }}" method="POST" class="inline"
      data-confirm="Remove {{ $extension->extension_id }}? This deletes its files and settings and rolls back its database migrations."
      data-confirm-keep="Remove {{ $extension->extension_id }} files while keeping its settings and database tables?"
      data-progress="Removing extension… Please keep this page open.">
    @csrf
    <label style="font-weight: normal; font-size: 12px; margin: 0 6px;" title="Preserve settings and database tables when removing the extension">
        <input type="checkbox" name="keep_data" value="1" aria-label="Keep data for {{ $extension->extension_id }}"> Keep data
    </label>
    <button type="submit" class="btn {{ ($compact ?? false) ? 'btn-xs' : '' }} btn-danger" title="Remove" aria-label="Remove {{ $extension->extension_id }}">
        <i class="fa fa-trash" aria-hidden="true"></i>@if(!($compact ?? false)) Remove @endif
    </button>
</form>
