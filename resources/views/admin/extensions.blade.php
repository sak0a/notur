@extends('layouts.admin')

@section('title')
    Extension Management
@endsection

@section('content-header')
    <h1>Extensions<small>Manage Notur extensions</small></h1>
    <div class="pull-right -mt-[35px]">
        <a href="{{ route('admin.notur.health') }}" class="btn btn-default btn-sm">
            <i class="fa fa-heartbeat"></i> Health
        </a>
        <a href="{{ route('admin.notur.diagnostics') }}" class="btn btn-default btn-sm">
            <i class="fa fa-stethoscope"></i> Diagnostics
        </a>
    </div>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Extensions</li>
    </ol>
@endsection

@section('content')
    @include('notur::admin.partials.brutalist-styles')

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
                    <h3 class="box-title"><i class="fa fa-plus" style="margin-right: 8px; opacity: 0.5;"></i>Install Extension</h3>
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
<<<<<<< claude/brutalist-admin-redesign-SiZm3
                            <div class="col-md-1 text-center" style="padding-top: 30px;">
                                <span style="color: var(--nb-text-muted, #555); font-family: var(--nb-mono, monospace); font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em;">or</span>
=======
                            <div class="col-md-1 text-center pt-[30px]">
                                <strong>OR</strong>
>>>>>>> master
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="archive">Upload .notur File</label>
                                    <input type="file" class="form-control" id="archive" name="archive" accept=".notur">
                                    <p class="help-block">Upload a .notur archive file to install.</p>
                                </div>
                            </div>
                            <div class="col-md-1 pt-[25px]">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-download"></i> Install
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Search Registry --}}
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-search" style="margin-right: 8px; opacity: 0.5;"></i>Search Registry</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                </div>
                <div class="box-body">
                    <form action="{{ route('admin.notur.extensions') }}" method="GET">
                        <div class="row">
                            <div class="col-md-10">
                                <div class="form-group">
                                    <label for="registry_search">Search</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="registry_search"
                                        name="q"
                                        value="{{ $registryQuery ?? '' }}"
                                        placeholder="Search by name, ID, tag, or description"
                                    >
                                    <p class="help-block">Results come from the Notur registry index.</p>
                                </div>
                            </div>
                            <div class="col-md-2 pt-[25px]">
                                <button type="submit" class="btn btn-info btn-block">
                                    <i class="fa fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>

                    @if(!empty($registryError))
                        <div class="alert alert-danger mt-[15px]">
                            Registry search failed: {{ $registryError }}
                        </div>
                    @endif

                    @if(!empty($registryQuery))
                        @if(empty($registryResults))
                            <div class="callout callout-info mt-[15px]">
                                <p>No registry results found for <strong>{{ $registryQuery }}</strong>.</p>
                            </div>
                        @else
                            <div class="table-responsive mt-[10px]">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Extension</th>
                                            <th>ID</th>
                                            <th>Latest</th>
                                            <th>Description</th>
                                            <th>Tags</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($registryResults as $result)
                                            @php
                                                $resultId = $result['id'] ?? '';
                                                $resultTags = $result['tags'] ?? [];
                                                $resultVersion = $result['latest_version'] ?? ($result['version'] ?? 'N/A');
                                            @endphp
                                            <tr>
                                                <td>{{ $result['name'] ?? 'Unnamed Extension' }}</td>
                                                <td><code>{{ $resultId }}</code></td>
                                                <td>{{ $resultVersion }}</td>
                                                <td>
                                                    @if(!empty($result['description']))
                                                        <small>{{ \Illuminate\Support\Str::limit($result['description'], 80) }}</small>
                                                    @else
                                                        <small class="text-muted">No description</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(!empty($resultTags))
                                                        @foreach($resultTags as $tag)
                                                            <span class="label label-default">{{ $tag }}</span>
                                                        @endforeach
                                                    @else
                                                        <small class="text-muted">None</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if(!empty($installedIds) && in_array($resultId, $installedIds, true))
                                                        <span class="label label-success">Installed</span>
                                                    @else
                                                        <form action="{{ route('admin.notur.extensions.install') }}" method="POST" class="inline">
                                                            @csrf
                                                            <input type="hidden" name="registry_id" value="{{ $resultId }}">
                                                            <button type="submit" class="btn btn-xs btn-success" title="Install">
                                                                <i class="fa fa-download"></i> Install
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Installed Extensions --}}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-puzzle-piece" style="margin-right: 8px; opacity: 0.5;"></i>Installed Extensions</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('admin.notur.slots') }}" class="btn btn-xs btn-default">
                            <i class="fa fa-th"></i> Slots
                        </a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    @if($extensions->isEmpty())
                        <div class="callout callout-info m-[15px]">
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
                                        <td style="font-family: var(--nb-mono, monospace); font-size: 12px;">{{ $extension->version }}</td>
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
                                            <small style="font-family: var(--nb-mono, monospace);">{{ $extension->created_at ? $extension->created_at->format('Y-m-d') : 'N/A' }}</small>
                                        </td>
                                        <td>
                                            @if($extension->enabled)
                                                <span class="label label-success"><span class="nb-status nb-status--active"></span>Enabled</span>
                                            @else
                                                <span class="label label-default"><span class="nb-status nb-status--inactive"></span>Disabled</span>
                                            @endif
                                        </td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            <a href="{{ route('admin.notur.extensions.show', $extension->extension_id) }}" class="btn btn-xs btn-primary" title="Details">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            @if($extension->enabled)
                                                <form action="{{ route('admin.notur.extensions.disable', $extension->extension_id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-warning" title="Disable">
                                                        <i class="fa fa-pause"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.notur.extensions.enable', $extension->extension_id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success" title="Enable">
                                                        <i class="fa fa-play"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('admin.notur.extensions.remove', $extension->extension_id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove {{ $extension->extension_id }}? This will delete all extension files and roll back migrations.');">
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

            {{-- Notur Brand --}}
            <div class="nb-brand-bar">
                <div class="nb-brand-bar__logo">N</div>
                <div class="nb-brand-bar__text">Notur Extension Framework</div>
            </div>
        </div>
    </div>
@endsection
