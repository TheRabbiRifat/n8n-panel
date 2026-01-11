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
    <div>
        <a href="{{ route('containers.logs.download', $container->id) }}" class="btn btn-outline-secondary me-2">
            <i class="bi bi-download"></i> Download Logs
        </a>
        <a href="{{ route('instances.index') }}" class="btn btn-secondary">Back to Instances</a>
    </div>
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

                        <!-- Live Stats -->
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body py-2">
                                <div class="row text-center">
                                    <div class="col-6 border-end">
                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">CPU Usage</small>
                                        <div class="h5 mb-0" id="stat-cpu">--%</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">RAM Usage</small>
                                        <div class="h5 mb-0" id="stat-mem">--</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            @php
                                $isRunning = false;
                                if (isset($stats['State']['Status']) && $stats['State']['Status'] === 'running') {
                                    $isRunning = true;
                                } elseif (isset($stats['State']['Running']) && $stats['State']['Running'] === true) {
                                    $isRunning = true;
                                }
                            @endphp

                            <span id="status-badge" class="badge {{ $isRunning ? 'bg-success' : 'bg-danger' }} fs-6 px-3 py-2">
                                {{ $isRunning ? 'Running' : 'Stopped' }}
                            </span>
                            <span class="ms-2 text-muted">Since {{ $container->updated_at->diffForHumans() }}</span>
                        </div>

                        <div class="d-flex gap-2 mb-4" id="actions-wrapper">
                            @if($isRunning)
                                <button class="btn btn-warning" onclick="performAction('stop')"><i class="bi bi-stop-circle"></i> Stop</button>
                                <button class="btn btn-info text-white" onclick="performAction('restart')"><i class="bi bi-arrow-clockwise"></i> Restart</button>
                            @else
                                <button class="btn btn-success" onclick="performAction('start')"><i class="bi bi-play-circle"></i> Start</button>
                            @endif

                            <form action="{{ route('instances.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this instance?');" class="d-inline">
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
                                <td>{{ $container->package->ram_limit }} GB</td>
                            </tr>
                            <tr>
                                <th>Disk Limit:</th>
                                <td>{{ $container->package->disk_limit ?? 'Unl' }} GB</td>
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="image_tag" class="form-label">n8n Version</label>
                            <select class="form-select" name="image_tag" id="image_tag">
                                @foreach($versions as $version)
                                    <option value="{{ $version }}" {{ ($container->image_tag ?? 'latest') == $version ? 'selected' : '' }}>{{ $version }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="package_id" class="form-label">Resource Package</label>
                            <select class="form-select" name="package_id" id="package_id">
                                @foreach($packages as $pkg)
                                    <option value="{{ $pkg->id }}" {{ $container->package_id == $pkg->id ? 'selected' : '' }}>
                                        {{ $pkg->name }} ({{ $pkg->cpu_limit }} CPU, {{ $pkg->ram_limit }} GB, {{ $pkg->disk_limit ?? 'Unl' }} GB)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @php
                        $currentEnv = $container->environment ? json_decode($container->environment, true) : [];
                        $currentTimezone = $currentEnv['GENERIC_TIMEZONE'] ?? 'Asia/Dhaka';
                    @endphp

                    <div class="mb-3">
                        <label for="generic_timezone" class="form-label">Timezone</label>
                        <select class="form-select" name="generic_timezone" id="generic_timezone">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" {{ $currentTimezone == $tz ? 'selected' : '' }}>{{ $tz }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Select the timezone for this n8n instance.</div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                        <div>
                            Saving changes will <strong>recreate</strong> the instance container. A brief downtime will occur.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save & Apply Changes</button>
                </form>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="d-flex justify-content-end mb-2">
                    <button class="btn btn-sm btn-outline-primary" id="copy-logs-btn"><i class="bi bi-clipboard"></i> Copy All Logs</button>
                </div>
                <div id="logs-terminal" style="height: 500px; width: 100%; overflow: hidden; padding: 0; margin: 0;"></div>
                <style>
                    #logs-terminal .xterm-viewport {
                        overflow-y: auto !important;
                    }
                </style>
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
            convertEol: true,
            scrollback: 1000,
            disableStdin: true
        });
        const fitAddonLogs = new FitAddon.FitAddon();
        logsTerm.loadAddon(fitAddonLogs);
        logsTerm.open(document.getElementById('logs-terminal'));

        // Ensure fit is called after render
        setTimeout(() => fitAddonLogs.fit(), 100);

        // Handle Window Resize
        window.addEventListener('resize', () => {
            fitAddonLogs.fit();
        });

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
            // Re-fit when tab becomes visible
            setTimeout(() => fitAddonLogs.fit(), 50);
            fetchLogs();
            logsInterval = setInterval(fetchLogs, 3000); // Poll every 3s
        });

        logsTabBtn.addEventListener('hidden.bs.tab', function() {
            clearInterval(logsInterval);
        });

        // Copy Logs
        document.getElementById('copy-logs-btn').addEventListener('click', function() {
            logsTerm.selectAll();
            const text = logsTerm.getSelection();
            logsTerm.clearSelection();

            navigator.clipboard.writeText(text).then(() => {
                const btn = this;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i> Copied';
                setTimeout(() => btn.innerHTML = originalHtml, 2000);
            });
        });

        // Live Stats
        function fetchStats() {
            fetch(`/containers/${containerId}/stats`)
                .then(r => r.json())
                .then(data => {
                    if(data) {
                        document.getElementById('stat-cpu').innerText = data.CPUPerc || '--%';
                        document.getElementById('stat-mem').innerText = data.MemUsage || '--';
                    }
                })
                .catch(e => console.log('Stats error', e));
        }
        // Poll every 3 seconds
        setInterval(fetchStats, 3000);
        fetchStats();

        // AJAX Action Handler
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        window.performAction = function(action) {
            // Disable buttons
            const wrapper = document.getElementById('actions-wrapper');
            const buttons = wrapper.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);

            fetch(`/containers/${containerId}/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI based on action
                    const isRunning = (action === 'start' || action === 'restart');
                    updateUI(isRunning);
                } else {
                    alert('Error: ' + data.message);
                    buttons.forEach(btn => btn.disabled = false);
                }
            })
            .catch(error => {
                console.error(error);
                alert('An unexpected error occurred.');
                buttons.forEach(btn => btn.disabled = false);
            });
        };

        function updateUI(isRunning) {
            const badge = document.getElementById('status-badge');
            const wrapper = document.getElementById('actions-wrapper');

            if (isRunning) {
                badge.className = 'badge bg-success fs-6 px-3 py-2';
                badge.innerText = 'Running';

                wrapper.innerHTML = `
                    <button class="btn btn-warning" onclick="performAction('stop')"><i class="bi bi-stop-circle"></i> Stop</button>
                    <button class="btn btn-info text-white" onclick="performAction('restart')"><i class="bi bi-arrow-clockwise"></i> Restart</button>
                    <form action="{{ route('instances.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this instance?');" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                `;
            } else {
                badge.className = 'badge bg-danger fs-6 px-3 py-2';
                badge.innerText = 'Stopped';

                wrapper.innerHTML = `
                    <button class="btn btn-success" onclick="performAction('start')"><i class="bi bi-play-circle"></i> Start</button>
                    <form action="{{ route('instances.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this instance?');" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                `;
            }
        }
    });
</script>
@endsection
