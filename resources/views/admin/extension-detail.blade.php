@extends('layouts.admin')

@section('title')
    Extension: {{ $extension->name }}
@endsection

@section('content-header')
    <h1>{{ $extension->name }}<small>Extension Details</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.notur.extensions') }}">Extensions</a></li>
        <li class="active">{{ $extension->name }}</li>
    </ol>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>There were issues saving settings.</strong>
            <ul style="margin-top: 8px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        {{-- Extension Overview --}}
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Overview</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <tr>
                            <th style="width: 30%;">Extension ID</th>
                            <td><code>{{ $extension->extension_id }}</code></td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td>{{ $extension->name }}</td>
                        </tr>
                        <tr>
                            <th>Version</th>
                            <td>{{ $extension->version }}</td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td>{{ $manifest['description'] ?? 'No description' }}</td>
                        </tr>
                        <tr>
                            <th>Authors</th>
                            <td>
                                @if(!empty($manifest['authors']))
                                    @foreach($manifest['authors'] as $author)
                                        @if(is_array($author))
                                            {{ $author['name'] ?? '' }}{{ isset($author['email']) ? ' <' . $author['email'] . '>' : '' }}
                                        @else
                                            {{ $author }}
                                        @endif
                                        @if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>License</th>
                            <td>{{ $manifest['license'] ?? 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <th>Entrypoint</th>
                            <td><code>{{ $manifest['entrypoint'] ?? 'N/A' }}</code></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if($extension->enabled)
                                    <span class="label label-success">Enabled</span>
                                @else
                                    <span class="label label-default">Disabled</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Installed</th>
                            <td>{{ $extension->created_at ? $extension->created_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="box-footer">
                    @if($extension->enabled)
                        <form action="{{ route('admin.notur.extensions.disable', $extension->extension_id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="fa fa-pause"></i> Disable
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.notur.extensions.enable', $extension->extension_id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-play"></i> Enable
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('admin.notur.extensions.remove', $extension->extension_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove {{ $extension->extension_id }}?');">
                        @csrf
                        <button type="submit" class="btn btn-danger pull-right">
                            <i class="fa fa-trash"></i> Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Dependencies & Permissions --}}
        <div class="col-md-6">
            {{-- Dependencies --}}
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Dependencies</h3>
                </div>
                <div class="box-body">
                    @if(!empty($dependencies))
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Extension</th>
                                    <th>Required Version</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dependencies as $depId => $depVersion)
                                    <tr>
                                        <td><code>{{ $depId }}</code></td>
                                        <td>{{ $depVersion }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No dependencies.</p>
                    @endif
                </div>
            </div>

            {{-- Permissions --}}
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Permissions</h3>
                </div>
                <div class="box-body">
                    @if(!empty($permissions))
                        <ul class="list-group">
                            @foreach($permissions as $permission)
                                @if(is_array($permission))
                                    <li class="list-group-item">
                                        <strong>{{ $permission['key'] ?? $permission['name'] ?? 'Unknown' }}</strong>
                                        @if(isset($permission['description']))
                                            <br><small class="text-muted">{{ $permission['description'] }}</small>
                                        @endif
                                    </li>
                                @else
                                    <li class="list-group-item"><code>{{ $permission }}</code></li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">No permissions declared.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($healthDefinitions) || !empty($healthResults))
        <div class="row">
            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Health Checks</h3>
                    </div>
                    <div class="box-body">
                        @if(!empty($healthDefinitions))
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 180px;">Check</th>
                                        <th style="width: 120px;">Status</th>
                                        <th style="width: 120px;">Severity</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($healthDefinitions as $definition)
                                        @php
                                            $checkId = $definition['id'] ?? '';
                                            $result = $healthResultMap[$checkId] ?? null;
                                            $status = $result['status'] ?? 'unknown';
                                            $statusClass = match ($status) {
                                                'ok' => 'label-success',
                                                'warning' => 'label-warning',
                                                'error' => 'label-danger',
                                                default => 'label-default',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $definition['label'] ?? $checkId }}</strong>
                                                @if(!empty($definition['description']))
                                                    <br><small class="text-muted">{{ $definition['description'] }}</small>
                                                @endif
                                            </td>
                                            <td><span class="label {{ $statusClass }}">{{ $status }}</span></td>
                                            <td>{{ $definition['severity'] ?? 'normal' }}</td>
                                            <td>{{ $result['message'] ?? 'Not reported' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted">No health checks declared in the manifest.</p>
                        @endif

                        @if(!empty($healthResults))
                            @php
                                $unknownResults = array_filter($healthResults, function ($result) use ($healthResultMap, $healthDefinitions) {
                                    $id = $result['id'] ?? null;
                                    if (!$id) return false;
                                    foreach ($healthDefinitions as $definition) {
                                        if (($definition['id'] ?? null) === $id) {
                                            return false;
                                        }
                                    }
                                    return true;
                                });
                            @endphp
                            @if(!empty($unknownResults))
                                <h4>Undeclared Results</h4>
                                <ul class="list-group">
                                    @foreach($unknownResults as $result)
                                        <li class="list-group-item">
                                            <strong>{{ $result['id'] }}</strong>
                                            <span class="label label-default" style="margin-left: 8px;">{{ $result['status'] ?? 'unknown' }}</span>
                                            @if(!empty($result['message']))
                                                <br><small class="text-muted">{{ $result['message'] }}</small>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($scheduleTasks))
        <div class="row">
            <div class="col-md-12">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Scheduled Tasks</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Task</th>
                                    <th style="width: 140px;">Cron</th>
                                    <th style="width: 160px;">Command</th>
                                    <th style="width: 140px;">Flags</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($scheduleTasks as $task)
                                    @php
                                        $flags = [];
                                        if (!empty($task['without_overlapping'])) $flags[] = 'no-overlap';
                                        if (!empty($task['on_one_server'])) $flags[] = 'one-server';
                                        if (!empty($task['run_in_maintenance'])) $flags[] = 'maintenance';
                                        if (isset($task['enabled']) && $task['enabled'] === false) $flags[] = 'disabled';
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $task['label'] ?? $task['id'] ?? 'Unnamed task' }}</strong>
                                        </td>
                                        <td><code>{{ $task['cron'] ?? '—' }}</code></td>
                                        <td><code>{{ $task['command'] ?? '—' }}</code></td>
                                        <td>
                                            @if(!empty($flags))
                                                {{ implode(', ', $flags) }}
                                            @else
                                                <span class="text-muted">none</span>
                                            @endif
                                        </td>
                                        <td>{{ $task['description'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($settingsSchema['fields']))
        <div class="row">
            <div class="col-md-8">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $settingsSchema['title'] ?? 'Settings' }}</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('admin.notur.extensions.settings.preview', $extension->extension_id) }}" class="btn btn-xs btn-default" target="_blank" rel="noopener">
                                <i class="fa fa-code"></i> Preview JSON
                            </a>
                        </div>
                    </div>
                    <form action="{{ route('admin.notur.extensions.settings', $extension->extension_id) }}" method="POST">
                        @csrf
                        <div class="box-body">
                            @if(!empty($settingsSchema['description']))
                                <p class="text-muted">{{ $settingsSchema['description'] }}</p>
                            @endif

                            @foreach($settingsSchema['fields'] as $field)
                                @php
                                    $fieldKey = $field['key'];
                                    $fieldId = 'settings_' . str_replace(['.', ' '], '_', $fieldKey);
                                    $defaultValue = $settingsValues[$fieldKey] ?? $field['default'] ?? null;
                                    $oldSettings = old('settings');
                                    if (!is_array($oldSettings)) {
                                        $oldSettings = null;
                                    }
                                    if (is_array($oldSettings) && array_key_exists($fieldKey, $oldSettings)) {
                                        $currentValue = $oldSettings[$fieldKey];
                                    } elseif (is_array($oldSettings) && ($field['type'] ?? '') === 'boolean') {
                                        $currentValue = false;
                                    } else {
                                        $currentValue = $defaultValue;
                                    }
                                    $inputType = $field['input'] ?? 'text';
                                    $allowedInputTypes = ['text', 'email', 'password', 'url', 'color', 'number'];
                                    if (!in_array($inputType, $allowedInputTypes, true)) {
                                        $inputType = 'text';
                                    }
                                @endphp
                                <div class="form-group {{ $errors->has($fieldKey) ? 'has-error' : '' }}">
                                    <label for="{{ $fieldId }}">
                                        {{ $field['label'] ?? $fieldKey }}
                                        @if(!empty($field['required']))
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>

                                    @if($field['type'] === 'boolean')
                                        <div class="checkbox">
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    id="{{ $fieldId }}"
                                                    name="settings[{{ $fieldKey }}]"
                                                    value="1"
                                                    {{ $currentValue ? 'checked' : '' }}
                                                >
                                                {{ $field['label'] ?? $fieldKey }}
                                            </label>
                                        </div>
                                    @elseif($field['type'] === 'text')
                                        <textarea
                                            class="form-control"
                                            id="{{ $fieldId }}"
                                            name="settings[{{ $fieldKey }}]"
                                            rows="4"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >{{ $currentValue }}</textarea>
                                    @elseif($field['type'] === 'select')
                                        <select class="form-control" id="{{ $fieldId }}" name="settings[{{ $fieldKey }}]">
                                            @if(empty($field['required']))
                                                <option value="">Select...</option>
                                            @endif
                                            @foreach($field['options'] ?? [] as $option)
                                                @php
                                                    $optionValue = (string) ($option['value'] ?? '');
                                                @endphp
                                                <option value="{{ $optionValue }}" {{ (string) $currentValue === $optionValue ? 'selected' : '' }}>
                                                    {{ $option['label'] ?? $optionValue }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input
                                            type="{{ $field['type'] === 'number' ? 'number' : $inputType }}"
                                            class="form-control"
                                            id="{{ $fieldId }}"
                                            name="settings[{{ $fieldKey }}]"
                                            value="{{ $currentValue }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >
                                    @endif

                                    @if(!empty($field['help']))
                                        <p class="help-block">{{ $field['help'] }}</p>
                                    @endif
                                    @if($errors->has($fieldKey))
                                        <p class="help-block">{{ $errors->first($fieldKey) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Activity Log</h3>
                </div>
                <div class="box-body">
                    @if($activityLogs->isNotEmpty())
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 160px;">Timestamp</th>
                                    <th style="width: 120px;">Action</th>
                                    <th>Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activityLogs as $log)
                                    <tr>
                                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                                        <td><span class="label label-default">{{ $log->action }}</span></td>
                                        <td>
                                            {{ $log->summary ?? '—' }}
                                            @if(!empty($log->context))
                                                <br><small class="text-muted">{{ json_encode($log->context) }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No activity logged yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Frontend Slots --}}
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Frontend Slots</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('admin.notur.slots') }}" class="btn btn-xs btn-default">
                            <i class="fa fa-th"></i> Slot Catalog
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    @if(!empty($slotRegistrations))
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Slot</th>
                                    <th>Component</th>
                                    <th>Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($slotRegistrations as $slot)
                                    <tr>
                                        <td><code>{{ $slot['slot'] }}</code></td>
                                        <td>
                                            @if(!empty($slot['component']))
                                                <span class="label label-default">{{ $slot['component'] }}</span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>{{ $slot['order'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No frontend slots registered.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Admin Routes --}}
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Admin Routes</h3>
                </div>
                <div class="box-body">
                    @if(!empty($adminRouteFile))
                        <p class="text-muted">Manifest route file: <code>{{ $adminRouteFile }}</code></p>
                    @endif

                    @if(!empty($adminRoutes))
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Methods</th>
                                    <th>URI</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adminRoutes as $route)
                                    <tr>
                                        <td>{{ implode(', ', $route['methods']) }}</td>
                                        <td><code>{{ $route['uri'] }}</code></td>
                                        <td>{{ $route['name'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No admin routes registered.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Migrations --}}
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Migrations</h3>
                </div>
                <div class="box-body">
                    @if(!empty($migrationStatus))
                        <ul class="list-group">
                            @foreach($migrationStatus as $migration)
                                <li class="list-group-item">
                                    <i class="fa fa-database text-green"></i> {{ $migration }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">No migrations.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Manifest Data --}}
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Raw Manifest</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                </div>
                <div class="box-body">
                    <pre style="max-height: 400px; overflow-y: auto;">{{ json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>

    {{-- Activity Log --}}
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Activity</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Date</th>
                                <th>Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($extension->updated_at && $extension->updated_at->ne($extension->created_at))
                                <tr>
                                    <td>{{ $extension->updated_at->format('Y-m-d H:i:s') }}</td>
                                    <td>Extension updated (status: {{ $extension->enabled ? 'enabled' : 'disabled' }})</td>
                                </tr>
                            @endif
                            @if($extension->created_at)
                                <tr>
                                    <td>{{ $extension->created_at->format('Y-m-d H:i:s') }}</td>
                                    <td>Extension installed (v{{ $extension->version }})</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                    @if(!$extension->created_at)
                        <p class="text-muted">No activity recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
