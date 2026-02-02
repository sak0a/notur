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
