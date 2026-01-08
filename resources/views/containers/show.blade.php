@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-1">{{ $container->name }}</h2>
        <div class="text-muted small">
            ID: <span class="text-monospace">{{ $container->docker_id }}</span> &bull;
            Image: {{ $stats['image'] ?? 'Unknown' }}
        </div>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="containerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">Logs</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="containerTabsContent">

            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title mb-4">Status & Actions</h5>
                        <div class="mb-4">
                            @if(isset($stats['state']) && str_contains($stats['state'], 'running'))
                                <span class="badge bg-success fs-6 px-3 py-2">Running</span>
                            @else
                                <span class="badge bg-danger fs-6 px-3 py-2">Stopped</span>
                            @endif
                            <span class="ms-2 text-muted">Since {{ $container->updated_at->diffForHumans() }}</span>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            @if(isset($stats['state']) && str_contains($stats['state'], 'running'))
                                <form action="{{ route('containers.stop', $container->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-warning"><i class="bi bi-stop-circle"></i> Stop</button>
                                </form>
                                <form action="{{ route('containers.restart', $container->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-info text-white"><i class="bi bi-arrow-clockwise"></i> Restart</button>
                                </form>
                            @else
                                <form action="{{ route('containers.start', $container->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-success"><i class="bi bi-play-circle"></i> Start</button>
                                </form>
                            @endif

                            <form action="{{ route('containers.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this container?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="card-title mb-4">Configuration</h5>
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 150px;">Port:</th>
                                <td><a href="http://{{ request()->getHost() }}:{{ $container->port }}" target="_blank">{{ $container->port }} <i class="bi bi-box-arrow-up-right small"></i></a></td>
                            </tr>
                            <tr>
                                <th>Package:</th>
                                <td>{{ $container->package->name ?? 'None' }}</td>
                            </tr>
                            @if($container->package)
                            <tr>
                                <th>CPU Limit:</th>
                                <td>{{ $container->package->cpu_limit }} CPUs</td>
                            </tr>
                            <tr>
                                <th>RAM Limit:</th>
                                <td>{{ $container->package->ram_limit }} MB</td>
                            </tr>
                            @endif
                            <tr>
                                <th>Version:</th>
                                <td>{{ $container->image_tag ?? 'latest' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <form action="{{ route('containers.update', $container->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <h5 class="card-title">General</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="image_tag" class="form-label">n8n Version</label>
                                <select class="form-select" name="image_tag" id="image_tag">
                                    @foreach($versions as $v)
                                        <option value="{{ $v }}" {{ ($container->image_tag ?? 'latest') == $v ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Changing version will recreate the container.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="card-title">Environment Variables</h5>
                        <label for="environment" class="form-label">Key=Value (One per line)</label>
                        <textarea class="form-control font-monospace" id="environment" name="environment" rows="10" placeholder="DB_HOST=localhost&#10;DB_PORT=5432">
@if($container->environment)
@foreach($container->environment as $key => $value)
{{ $key }}={{ $value }}
@endforeach
@endif
</textarea>
                        <div class="form-text">These variables are permanent. Updating them will recreate the container.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save & Apply Changes</button>
                </form>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Container Logs (Tail 100)</h5>
                    <button class="btn btn-sm btn-outline-secondary" id="refreshLogsBtn"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                </div>
                <div class="bg-dark text-white p-3 rounded" style="height: 500px; overflow-y: scroll; font-family: monospace; font-size: 0.85rem;" id="logContainer">
                    <pre class="m-0" id="logContent">{{ $logs }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logContent = document.getElementById('logContent');
        const refreshBtn = document.getElementById('refreshLogsBtn');
        const containerId = {{ $container->id }};

        refreshBtn.addEventListener('click', function() {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';

            fetch(`/containers/${containerId}/logs`)
                .then(response => response.json())
                .then(data => {
                    logContent.textContent = data.logs;
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Refresh';
                })
                .catch(err => {
                    console.error('Error fetching logs:', err);
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error';
                });
        });
    });
</script>
@endsection
