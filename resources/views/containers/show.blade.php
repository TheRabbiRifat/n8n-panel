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
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">Live Logs</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="terminal-tab" data-bs-toggle="tab" data-bs-target="#terminal" type="button" role="tab">Terminal</button>
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

                            <form action="{{ route('containers.destroy', $container->id) }}" method="POST" onsubmit="return confirm('Delete this instance?');">
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
                                <div class="form-text">Changing version will recreate the instance.</div>
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
                        <div class="form-text">These variables are permanent. Updating them will recreate the instance.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save & Apply Changes</button>
                </form>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div id="logs-terminal" style="height: 500px; width: 100%;"></div>
            </div>

            <!-- Terminal Tab -->
             <div class="tab-pane fade" id="terminal" role="tabpanel">
                <div class="mb-2">
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-white border-dark">$</span>
                        <input type="text" id="terminal-input" class="form-control font-monospace bg-light" placeholder="Type command and press Enter (e.g. ls -la, env, id)">
                        <button class="btn btn-primary" id="terminal-send">Send</button>
                    </div>
                </div>
                <div id="exec-terminal" style="height: 500px; width: 100%;"></div>
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

        // --- Exec Terminal (Xterm) ---
        const execTerm = new Terminal({
            cursorBlink: true,
            theme: { background: '#1e1e1e' },
            convertEol: true
        });
        const fitAddonExec = new FitAddon.FitAddon();
        execTerm.loadAddon(fitAddonExec);
        execTerm.open(document.getElementById('exec-terminal'));
        fitAddonExec.fit();

        execTerm.write("Welcome to n8n Interactive Shell (Simulated)\r\nType a command in the input box above.\r\n\r\n");

        const terminalTabBtn = document.getElementById('terminal-tab');
        terminalTabBtn.addEventListener('shown.bs.tab', function() {
            fitAddonExec.fit();
        });

        const terminalInput = document.getElementById('terminal-input');
        const terminalSend = document.getElementById('terminal-send');

        function sendCommand() {
            const cmd = terminalInput.value;
            if(!cmd) return;

            execTerm.write(`$ ${cmd}\r\n`);
            terminalInput.value = '';

            fetch(`/containers/${containerId}/exec`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ command: cmd })
            })
            .then(res => res.json())
            .then(data => {
                if(data.output) {
                    execTerm.write(data.output.replace(/\n/g, '\r\n') + "\r\n");
                } else {
                    execTerm.write("(No output)\r\n");
                }
            })
            .catch(err => {
                execTerm.write(`Error: ${err}\r\n`);
            });
        }

        terminalSend.addEventListener('click', sendCommand);
        terminalInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendCommand();
        });
    });
</script>
@endsection
