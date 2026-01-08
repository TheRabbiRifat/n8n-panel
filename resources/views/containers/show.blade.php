@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css" />
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-1">{{ $container->name }}</h2>
        <div class="text-muted small">
            ID: <span class="text-monospace">{{ $container->docker_id }}</span> &bull;
            Image: {{ $stats['Config']['Image'] ?? ($stats['image'] ?? 'Unknown') }}
        </div>
    </div>
    <a href="{{ route('instances.index') }}" class="btn btn-secondary">Back to Instances</a>
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
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">Live Logs</button>
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
                            @php
                                $isRunning = false;
                                if (isset($stats['State']['Status']) && $stats['State']['Status'] === 'running') {
                                    $isRunning = true;
                                } elseif (isset($stats['State']['Running']) && $stats['State']['Running'] === true) {
                                    $isRunning = true;
                                }
                            @endphp

                            @if($isRunning)
                                <span class="badge bg-success fs-6 px-3 py-2">Running</span>
                            @else
                                <span class="badge bg-danger fs-6 px-3 py-2">Stopped</span>
                            @endif
                            <span class="ms-2 text-muted">Since {{ $container->updated_at->diffForHumans() }}</span>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            @if($isRunning)
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

                            <form action="{{ route('instances.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this instance?');">
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
                                <th style="width: 150px;">Domain:</th>
                                <td>
                                    @if($container->domain)
                                        <a href="https://{{ $container->domain }}" target="_blank" class="fw-bold">{{ $container->domain }} <i class="bi bi-box-arrow-up-right small"></i></a>
                                    @else
                                        <span class="text-muted">Not Assigned</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Internal Port:</th>
                                <td>{{ $container->port }}</td>
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
                                <div class="form-text">Changing version will recreate the instance.</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Environment variables are managed globally by the administrator.
                    </div>

                    <button type="submit" class="btn btn-primary">Save & Apply Changes</button>
                </form>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div id="logs-terminal" style="height: 500px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const containerId = {{ $container->id }};

        // --- Live Logs (Xterm) ---
        const logsTerm = new Terminal({
            cursorBlink: true,
            theme: { background: '#1e1e1e' },
            convertEol: true
        });
        const fitAddonLogs = new FitAddon.FitAddon();
        logsTerm.loadAddon(fitAddonLogs);
        logsTerm.open(document.getElementById('logs-terminal'));
        fitAddonLogs.fit();

        let logsInterval;
        const logsTabBtn = document.getElementById('logs-tab');

        function fetchLogs() {
            fetch(`/containers/${containerId}/logs`)
                .then(response => response.json())
                .then(data => {
                    logsTerm.clear(); // Simple refresh strategy
                    logsTerm.write(data.logs.replace(/\n/g, '\r\n'));
                })
                .catch(err => console.error(err));
        }

        logsTabBtn.addEventListener('shown.bs.tab', function() {
            fitAddonLogs.fit();
            fetchLogs();
            logsInterval = setInterval(fetchLogs, 3000); // Poll every 3s
        });

        logsTabBtn.addEventListener('hidden.bs.tab', function() {
            clearInterval(logsInterval);
        });
    });
</script>
@endsection
