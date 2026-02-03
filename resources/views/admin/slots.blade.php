@extends('layouts.admin')

@section('title')
    Notur Slots
@endsection

@section('content-header')
    <h1>Notur Slots<small>Slot catalog and registrations</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.notur.extensions') }}">Extensions</a></li>
        <li class="active">Slots</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Slot Catalog</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Slot ID</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Container ID</th>
                                <th>Registered Extensions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($definitions as $def)
                                @php
                                    $slotId = $def['id'];
                                    $entries = $usage[$slotId] ?? [];
                                @endphp
                                <tr>
                                    <td><code>{{ $slotId }}</code></td>
                                    <td>{{ $def['type'] }}</td>
                                    <td>{{ $def['description'] }}</td>
                                    <td><code>notur-slot-{{ $slotId }}</code></td>
                                    <td>
                                        @if(empty($entries))
                                            <span class="text-muted">None</span>
                                        @else
                                            @foreach($entries as $entry)
                                                <div style="margin-bottom: 6px;">
                                                    <code>{{ $entry['extensionId'] }}</code>
                                                    @if(!empty($entry['component']))
                                                        <span class="label label-default">{{ $entry['component'] }}</span>
                                                    @endif
                                                    @if(!empty($entry['label']))
                                                        <small class="text-muted">{{ $entry['label'] }}</small>
                                                    @endif
                                                    @if(!empty($entry['permission']))
                                                        <small class="text-muted">({{ $entry['permission'] }})</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($unknown))
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Unknown Slot Registrations</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">These slots are registered by extensions but are not in the built-in slot catalog.</p>
                        @foreach($unknown as $slotId => $entries)
                            <div style="margin-bottom: 12px;">
                                <strong><code>{{ $slotId }}</code></strong>
                                @foreach($entries as $entry)
                                    <div style="margin-top: 4px;">
                                        <code>{{ $entry['extensionId'] }}</code>
                                        @if(!empty($entry['component']))
                                            <span class="label label-default">{{ $entry['component'] }}</span>
                                        @endif
                                        @if(!empty($entry['label']))
                                            <small class="text-muted">{{ $entry['label'] }}</small>
                                        @endif
                                        @if(!empty($entry['permission']))
                                            <small class="text-muted">({{ $entry['permission'] }})</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
