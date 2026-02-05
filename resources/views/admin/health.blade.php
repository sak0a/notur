@extends('layouts.admin')

@section('title')
    Notur Health
@endsection

@section('content-header')
    <h1>Notur Health<small>Extension health checks</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.notur.extensions') }}">Extensions</a></li>
        <li class="active">Health</li>
    </ol>
@endsection

@section('content')
    @php
        $totals = ['ok' => 0, 'warning' => 0, 'error' => 0, 'unknown' => 0];
        $criticalTotal = 0;
        $extensionsWithChecks = 0;

        foreach ($healthData as $entry) {
            $definitions = $entry['healthDefinitions'] ?? [];
            if (!empty($definitions)) {
                $extensionsWithChecks++;
            }
            foreach ($entry['statusCounts'] ?? [] as $status => $count) {
                $totals[$status] = ($totals[$status] ?? 0) + $count;
            }
            $criticalTotal += (int) ($entry['criticalFailures'] ?? 0);
        }
    @endphp

    <div class="row">
        <div class="col-xs-12">
            <div class="callout {{ $criticalTotal > 0 ? 'callout-danger' : 'callout-info' }}">
                <p>
                    <strong>Extensions with health checks:</strong> {{ $extensionsWithChecks }}
                    &nbsp;|&nbsp;
                    <strong>Checks:</strong> {{ array_sum($totals) }}
                    &nbsp;|&nbsp;
                    <strong>OK:</strong> {{ $totals['ok'] }}
                    &nbsp;|&nbsp;
                    <strong>Warnings:</strong> {{ $totals['warning'] }}
                    &nbsp;|&nbsp;
                    <strong>Errors:</strong> {{ $totals['error'] }}
                    &nbsp;|&nbsp;
                    <strong>Critical failures:</strong> {{ $criticalTotal }}
                </p>
            </div>
        </div>
    </div>

    @foreach($healthData as $entry)
        @php
            $extension = $entry['extension'];
            $manifest = $entry['manifest'] ?? [];
            $healthDefinitions = $entry['healthDefinitions'] ?? [];
            $healthResults = $entry['healthResults'] ?? [];
            $healthResultMap = $entry['healthResultMap'] ?? [];
            $counts = $entry['statusCounts'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0, 'unknown' => 0];

            $overallStatus = 'unknown';
            if (($counts['error'] ?? 0) > 0) {
                $overallStatus = 'error';
            } elseif (($counts['warning'] ?? 0) > 0) {
                $overallStatus = 'warning';
            } elseif (($counts['ok'] ?? 0) > 0) {
                $overallStatus = 'ok';
            }

            $overallClass = match ($overallStatus) {
                'ok' => 'label-success',
                'warning' => 'label-warning',
                'error' => 'label-danger',
                default => 'label-default',
            };
        @endphp

        <div class="row">
            <div class="col-xs-12">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            {{ $extension->name }}
                            <small><code>{{ $extension->extension_id }}</code></small>
                        </h3>
                        <div class="box-tools pull-right">
                            <span class="label {{ $overallClass }} mr-2">{{ $overallStatus }}</span>
                            @if($extension->enabled)
                                <span class="label label-success mr-2">Enabled</span>
                            @else
                                <span class="label label-default mr-2">Disabled</span>
                            @endif
                            <a href="{{ route('admin.notur.extensions.show', $extension->extension_id) }}" class="btn btn-xs btn-default">
                                <i class="fa fa-eye"></i> Details
                            </a>
                        </div>
                    </div>
                    <div class="box-body">
                        @if(empty($healthDefinitions) && empty($healthResults))
                            <p class="text-muted">No health checks declared or reported.</p>
                        @else
                            @if(!empty($healthDefinitions))
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th class="w-[200px]">Check</th>
                                            <th class="w-[120px]">Status</th>
                                            <th class="w-[140px]">Severity</th>
                                            <th>Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($healthDefinitions as $definition)
                                            @php
                                                $checkId = $definition['id'] ?? '';
                                                $result = $healthResultMap[$checkId] ?? null;
                                                $status = $result['status'] ?? 'unknown';
                                                $severity = strtolower((string) ($definition['severity'] ?? 'normal'));
                                                $statusClass = match ($status) {
                                                    'ok' => 'label-success',
                                                    'warning' => 'label-warning',
                                                    'error' => 'label-danger',
                                                    default => 'label-default',
                                                };
                                                $rowClass = '';
                                                if ($severity === 'critical' && in_array($status, ['warning', 'error'], true)) {
                                                    $rowClass = 'danger';
                                                } elseif ($status === 'error') {
                                                    $rowClass = 'danger';
                                                } elseif ($status === 'warning') {
                                                    $rowClass = 'warning';
                                                }
                                            @endphp
                                            <tr class="{{ $rowClass }}">
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
                                    $unknownResults = array_filter($healthResults, function ($result) use ($healthDefinitions) {
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
                                                <strong>{{ $result['id'] ?? 'Unknown' }}</strong>
                                                <span class="label label-default ml-2">{{ $result['status'] ?? 'unknown' }}</span>
                                                @if(!empty($result['message']))
                                                    <br><small class="text-muted">{{ $result['message'] }}</small>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
