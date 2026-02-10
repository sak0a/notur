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
    @include('notur::admin.partials.brutalist-styles')

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-th" style="margin-right: 8px; opacity: 0.5;"></i>Slot Catalog</h3>
                    <div class="box-tools pull-right">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <input type="text" class="form-control" id="slot-search" placeholder="Filter slots...">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="slot-search-clear">
                                    <i class="fa fa-times"></i>
                                </button>
                            </span>
                        </div>
                    </div>
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
                                    <td><span class="label label-default">{{ $def['type'] }}</span></td>
                                    <td>{{ $def['description'] }}</td>
                                    <td><code>notur-slot-{{ $slotId }}</code></td>
                                    <td>
                                        @if(empty($entries))
                                            <span class="text-muted">None</span>
                                        @else
                                            @foreach($entries as $entry)
                                                <div class="mb-1.5">
                                                    <code>{{ $entry['extensionId'] }}</code>
                                                    @if(!empty($entry['component']))
                                                        <span class="label label-default">{{ $entry['component'] }}</span>
                                                    @endif
                                                    @if(isset($entry['priority']))
                                                        <span class="label label-primary">p{{ $entry['priority'] ?? 0 }}</span>
                                                    @endif
                                                    @if(isset($entry['order']))
                                                        <span class="label label-default">o{{ $entry['order'] ?? 0 }}</span>
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
                            <tr id="slot-no-results" style="display: none;">
                                <td colspan="5" class="text-center text-muted" style="padding: 20px;">
                                    No slots match your search.
                                </td>
                            </tr>
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
                        <h3 class="box-title"><i class="fa fa-question-circle" style="margin-right: 8px; opacity: 0.5;"></i>Unknown Slot Registrations</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">These slots are registered by extensions but are not in the built-in slot catalog.</p>
                        @foreach($unknown as $slotId => $entries)
                            <div class="mb-3">
                                <strong><code>{{ $slotId }}</code></strong>
                                @foreach($entries as $entry)
                                    <div class="mt-1">
                                        <code>{{ $entry['extensionId'] }}</code>
                                        @if(!empty($entry['component']))
                                            <span class="label label-default">{{ $entry['component'] }}</span>
                                        @endif
                                        @if(isset($entry['priority']))
                                            <span class="label label-primary">p{{ $entry['priority'] ?? 0 }}</span>
                                        @endif
                                        @if(isset($entry['order']))
                                            <span class="label label-default">o{{ $entry['order'] ?? 0 }}</span>
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

    {{-- Notur Brand --}}
    <div class="nb-brand-bar">
        <div class="nb-brand-bar__logo">N</div>
        <div class="nb-brand-bar__text">Notur Extension Framework</div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('slot-search');
        var clearBtn = document.getElementById('slot-search-clear');
        var table = input.closest('.box').querySelector('table.table tbody');
        var rows = table.querySelectorAll('tr:not(#slot-no-results)');
        var noResults = document.getElementById('slot-no-results');

        function filterSlots() {
            var query = input.value.toLowerCase().trim();
            var visible = 0;
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                var match = !query || text.indexOf(query) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            noResults.style.display = visible === 0 ? '' : 'none';
        }

        input.addEventListener('input', filterSlots);
        clearBtn.addEventListener('click', function () {
            input.value = '';
            filterSlots();
            input.focus();
        });
    });
    </script>
@endsection
