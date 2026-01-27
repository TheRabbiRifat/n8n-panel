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
            @role('admin')
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="database-tab" data-bs-toggle="tab" data-bs-target="#database" type="button" role="tab">Database</button>
            </li>
            @endrole
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
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light fw-bold">Restore from Auto-Backup</div>
                                <div class="card-body">
                                    <p class="small text-muted">Select a backup file to restore. This will overwrite the current database.</p>
                                    <form action="{{ route('containers.db.restore', $container->id) }}" method="POST" onsubmit="return confirm('WARNING: This will overwrite the database with the selected backup. Continue?');">
                                        @csrf
                                        <div class="input-group mb-3">
                                            <select class="form-select" name="backup_path" required>
                                                <option value="" disabled selected>Select a backup...</option>
                                                @forelse($backups as $backup)
                                                    <option value="{{ $backup['path'] }}">{{ $backup['date'] }} ({{ $backup['size'] }})</option>
                                                @empty
                                                    <option value="" disabled>No backups found</option>
                                                @endforelse
                                            </select>
                                            <button class="btn btn-warning" type="submit">Restore</button>
                                        </div>
                                    </form>
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
                                <th>Database:</th>
                                <td>
                                    @if($container->db_database)
                                        <div class="font-monospace small text-muted">
                                            <div><i class="bi bi-database me-1"></i> {{ $container->db_database }}</div>
                                            <div><i class="bi bi-person me-1"></i> {{ $container->db_username }}</div>
                                        </div>
                                    @else
                                        <span class="text-muted">SQLite / Internal</span>
                                    @endif
                                </td>
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

                    @role('admin')
                    <div class="mb-3">
                        <label class="form-label fw-bold">Instance Ownership</label>
                        <div class="input-group">
                             <input type="text" class="form-control" value="{{ $container->user->name }} ({{ $container->user->email }})" readonly>
                             <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#transferOwnershipModal">
                                 <i class="bi bi-arrow-left-right"></i> Change Owner
                             </button>
                        </div>
                        <div class="form-text">Transfer this instance to another user.</div>
                    </div>
                    @endrole

                    @php
                        $currentEnv = $container->environment ? json_decode($container->environment, true) : [];
                        $currentTimezone = $currentEnv['GENERIC_TIMEZONE'] ?? config('app.timezone');
                        $encryptionKey = $currentEnv['N8N_ENCRYPTION_KEY'] ?? '';
                    @endphp

                    <div class="mb-3">
                        <label class="form-label">N8N Encryption Key <span class="badge bg-danger">CAUTION</span></label>
                        <input type="text" class="form-control font-monospace" name="N8N_ENCRYPTION_KEY" value="{{ $encryptionKey }}">
                        <div class="form-text text-danger">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> Changing this key will make existing credentials in n8n unreadable. Only change if restoring a backup.
                        </div>
                    </div>

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
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a href="{{ route('containers.logs.download', $container->id) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button class="btn btn-sm btn-outline-primary" id="copy-logs-btn">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                <div id="logs-terminal" style="height: 500px; width: 100%; overflow: hidden; padding: 0; margin: 0;"></div>
                <style>
                    #logs-terminal .xterm-viewport {
                        overflow-y: auto !important;
                    }
                </style>
            </div>

            <!-- Database Tab (Admin Only) -->
            @role('admin')
            <div class="tab-pane fade" id="database" role="tabpanel">
                @if($container->db_database)
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Database: <strong>{{ $container->db_database }}</strong> | User: <strong>{{ $container->db_username }}</strong>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light fw-bold">Export Database</div>
                                <div class="card-body">
                                    <p class="small text-muted">Download a SQL dump of the current database.</p>
                                    <a href="{{ route('containers.db.export', $container->id) }}" class="btn btn-primary">
                                        <i class="bi bi-download me-1"></i> Export .sql
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light fw-bold">Import Database</div>
                                <div class="card-body">
                                    <p class="small text-muted">Restore a SQL dump. <strong>Warning:</strong> This will overwrite existing data and restart the instance.</p>
                                    <form action="{{ route('containers.db.import', $container->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="input-group mb-3">
                                            <input type="file" class="form-control" name="sql_file" required accept=".sql,.txt,application/sql,text/plain">
                                            <button class="btn btn-danger" type="submit" onclick="return confirm('Overwrite database? This cannot be undone.')">Import</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning">
                        This instance does not use the managed PostgreSQL system (SQLite or Legacy).
                    </div>
                @endif
            </div>
            @endrole
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
        let currentLogData = '';
        const logsTabBtn = document.getElementById('logs-tab');

        function fetchLogs() {
            fetch(`/containers/${containerId}/logs`)
                .then(response => response.json())
                .then(data => {
                    logsTerm.clear(); // Simple refresh strategy
                    currentLogData = data.logs;
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
            try {
                if (!currentLogData) {
                    // Fallback to selection if variable is empty (e.g. initial load)
                    logsTerm.selectAll();
                    currentLogData = logsTerm.getSelection();
                    logsTerm.clearSelection();
                }

                if (!currentLogData) {
                    alert('Log buffer is empty.');
                    return;
                }

                const cleanLogs = currentLogData.replace(/\u001b\[[0-9;]*m/g, '');

                navigator.clipboard.writeText(cleanLogs).then(() => {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check"></i> Copied';
                    setTimeout(() => btn.innerHTML = originalHtml, 2000);
                }).catch(err => {
                    console.error('Clipboard write failed:', err);
                    alert('Failed to copy logs to clipboard. Please check browser permissions.');
                });
            } catch (e) {
                console.error('Copy logic error:', e);
            }
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
<!-- Ownership Transfer Modal -->
@role('admin')
<div class="modal fade" id="transferOwnershipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Instance Ownership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('instances.transfer', $container->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Select the new owner for this instance. The current owner will lose access.</p>
                    <div class="mb-3">
                        <label for="new_user_id" class="form-label">New Owner</label>
                        <select class="form-select" name="new_user_id" required>
                            <option value="">Select User...</option>
                            @foreach(\App\Models\User::all() as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Transfer Ownership</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole

@endsection
