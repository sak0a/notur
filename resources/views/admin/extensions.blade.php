@extends('layouts.admin')

@section('title')
    Extension Management
@endsection

@section('content-header')
    <h1>Extensions<small>Manage Notur extensions</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Extensions</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
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

            {{-- Install Extension --}}
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Install Extension</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                </div>
                <div class="box-body">
                    <form action="{{ route('admin.notur.extensions.install') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="registry_id">Registry ID</label>
                                    <input type="text" class="form-control" id="registry_id" name="registry_id" placeholder="vendor/extension-name">
                                    <p class="help-block">Enter the extension ID to install from the registry.</p>
                                </div>
                            </div>
                            <div class="col-md-1 text-center" style="padding-top: 30px;">
                                <strong>OR</strong>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="archive">Upload .notur File</label>
                                    <input type="file" class="form-control" id="archive" name="archive" accept=".notur">
                                    <p class="help-block">Upload a .notur archive file to install.</p>
                                </div>
                            </div>
                            <div class="col-md-1" style="padding-top: 25px;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-download"></i> Install
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Installed Extensions --}}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Installed Extensions</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    @if($extensions->isEmpty())
                        <div class="callout callout-info" style="margin: 15px;">
                            <p>No extensions are installed. Use the form above or the command line:</p>
                            <code>php artisan notur:install vendor/extension-name</code>
                        </div>
                    @else
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Extension</th>
                                    <th>ID</th>
                                    <th>Version</th>
                                    <th>Description</th>
                                    <th>Dependencies</th>
                                    <th>Installed</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($extensions as $extension)
                                    @php
                                        $manifest = $extension->manifest ?? [];
                                        $description = $manifest['description'] ?? '';
                                        $dependencies = $manifest['dependencies'] ?? [];
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.notur.extensions.show', $extension->extension_id) }}">
                                                {{ $extension->name }}
                                            </a>
                                        </td>
                                        <td><code>{{ $extension->extension_id }}</code></td>
                                        <td>{{ $extension->version }}</td>
                                        <td>
                                            @if($description)
                                                <small>{{ \Illuminate\Support\Str::limit($description, 60) }}</small>
                                            @else
                                                <small class="text-muted">No description</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($dependencies))
                                                @foreach($dependencies as $depId => $depVersion)
                                                    <span class="label label-info">{{ $depId }}: {{ $depVersion }}</span>
                                                @endforeach
                                            @else
                                                <small class="text-muted">None</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $extension->created_at ? $extension->created_at->format('Y-m-d') : 'N/A' }}</small>
                                        </td>
                                        <td>
                                            @if($extension->enabled)
                                                <span class="label label-success">Enabled</span>
                                            @else
                                                <span class="label label-default">Disabled</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('admin.notur.extensions.show', $extension->extension_id) }}" class="btn btn-xs btn-primary" title="Details">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            @if($extension->enabled)
                                                <form action="{{ route('admin.notur.extensions.disable', $extension->extension_id) }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-warning" title="Disable">
                                                        <i class="fa fa-pause"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.notur.extensions.enable', $extension->extension_id) }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success" title="Enable">
                                                        <i class="fa fa-play"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('admin.notur.extensions.remove', $extension->extension_id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove {{ $extension->extension_id }}? This will delete all extension files and roll back migrations.');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-danger" title="Remove">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
