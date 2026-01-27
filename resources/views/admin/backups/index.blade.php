@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Backup Settings</div>
            <div class="card-body">
                <form action="{{ route('admin.backups.update') }}" method="POST">
                    @csrf

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" {{ optional($setting)->enabled ? 'checked' : '' }}>
                        <label class="form-check-label" for="enabled">Enable Auto Backups</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Driver</label>
                        <select name="driver" class="form-select" id="driver-select" onchange="toggleFields()">
                            <option value="local" {{ optional($setting)->driver == 'local' ? 'selected' : '' }}>Local Storage</option>
                            <option value="ftp" {{ optional($setting)->driver == 'ftp' ? 'selected' : '' }}>FTP</option>
                            <option value="s3" {{ optional($setting)->driver == 's3' ? 'selected' : '' }}>S3 (AWS/MinIO)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schedule Frequency</label>
                        <select id="cron-type" class="form-select mb-2" onchange="toggleCron()">
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="custom">Custom Expression</option>
                        </select>

                        <div id="cron-time-wrapper" class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small text-muted">Hour (0-23)</label>
                                <select id="cron-hour" class="form-select form-select-sm" onchange="buildCron()"></select>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted">Minute (0-59)</label>
                                <select id="cron-min" class="form-select form-select-sm" onchange="buildCron()"></select>
                            </div>
                        </div>

                        <div id="cron-custom-wrapper" class="d-none">
                            <input type="text" name="cron_expression" id="cron_expression" class="form-control font-monospace" value="{{ optional($setting)->cron_expression ?? '0 2 * * *' }}" oninput="parseCronInput()">
                            <div class="form-text">Example: <code>0 2 * * *</code> (Daily at 2:00 AM)</div>
                        </div>
                        <!-- Hidden input to store value if not in custom mode (or synched) -->
                        <!-- Actually we can just use the name='cron_expression' on the input above and keep it updated -->
                    </div>

                    <!-- FTP Fields -->
                    <div id="ftp-fields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Host</label>
                            <input type="text" name="host" class="form-control" value="{{ optional($setting)->host }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Port</label>
                            <input type="text" name="port" class="form-control" value="{{ optional($setting)->port ?? 21 }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="{{ optional($setting)->username }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" value="{{ optional($setting)->password }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Encryption</label>
                            <select name="encryption" class="form-select">
                                <option value="">None</option>
                                <option value="ssl" {{ optional($setting)->encryption == 'ssl' ? 'selected' : '' }}>SSL/TLS</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Root Path</label>
                            <input type="text" name="path" class="form-control" value="{{ optional($setting)->path ?? '/' }}">
                        </div>
                    </div>

                    <!-- S3 Fields -->
                    <div id="s3-fields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Access Key (Username)</label>
                            <input type="text" name="username" class="form-control" value="{{ optional($setting)->username }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Secret Key (Password)</label>
                            <input type="password" name="password" class="form-control" value="{{ optional($setting)->password }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Region</label>
                            <input type="text" name="region" class="form-control" value="{{ optional($setting)->region ?? 'us-east-1' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bucket</label>
                            <input type="text" name="bucket" class="form-control" value="{{ optional($setting)->bucket }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Endpoint (Optional)</label>
                            <input type="text" name="endpoint" class="form-control" value="{{ optional($setting)->endpoint }}" placeholder="https://s3.amazonaws.com">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save Configuration</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">System Scheduler</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Ensure this single command is in your crontab to enable UI scheduling:</p>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace form-control-sm" value="* * * * * cd /var/n8n-panel && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" readonly id="cron-cmd">
                    <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cron-cmd').value)"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="mt-2">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle me-1"></i> Managed via UI</span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Manual Actions</div>
            <div class="card-body">
                <form action="{{ route('admin.backups.run') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-success w-100" onclick="return confirm('Start backup process now? This might take a while.')">
                        <i class="bi bi-play-circle me-1"></i> Trigger Backup Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Available Backups (Remote)</span>
                <span class="badge bg-secondary">{{ count($backups) }} Found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Instance / Folder</th>
                                <th class="text-center">Count</th>
                                <th>Last Backup</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $folder)
                            <tr data-bs-toggle="collapse" data-bs-target="#files-{{ Str::slug($folder['name']) }}" style="cursor: pointer;" class="accordion-toggle">
                                <td class="ps-4 fw-semibold">
                                    <i class="bi bi-folder-fill text-warning me-2"></i> {{ $folder['name'] }}
                                </td>
                                <td class="text-center"><span class="badge bg-secondary rounded-pill">{{ $folder['count'] }}</span></td>
                                <td class="text-muted small">{{ $folder['last_backup'] }}</td>
                                <td class="text-end pe-4">
                                    <i class="bi bi-chevron-down text-muted"></i>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="p-0 border-0">
                                    <div class="collapse bg-light" id="files-{{ Str::slug($folder['name']) }}">
                                        <div class="p-3">
                                            <table class="table table-sm mb-0 table-borderless">
                                                @foreach($folder['files'] as $file)
                                                <tr>
                                                    <td class="ps-4"><i class="bi bi-file-earmark-text me-2 text-muted"></i> {{ $file['name'] }}</td>
                                                    <td class="text-muted small">{{ $file['date'] }}</td>
                                                    <td class="text-end">
                                                        <a href="{{ route('admin.backups.download', ['path' => $file['path']]) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-archive fs-1 d-block opacity-25 mb-2"></i>
                                    No backups found on configured storage.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Driver Toggle
    function toggleFields() {
        const driver = document.getElementById('driver-select').value;
        const ftpFields = document.getElementById('ftp-fields');
        const s3Fields = document.getElementById('s3-fields');

        document.querySelectorAll('#ftp-fields input, #ftp-fields select').forEach(el => el.disabled = true);
        document.querySelectorAll('#s3-fields input, #s3-fields select').forEach(el => el.disabled = true);

        ftpFields.classList.add('d-none');
        s3Fields.classList.add('d-none');

        if (driver === 'ftp') {
            ftpFields.classList.remove('d-none');
            document.querySelectorAll('#ftp-fields input, #ftp-fields select').forEach(el => el.disabled = false);
        } else if (driver === 's3') {
            s3Fields.classList.remove('d-none');
            document.querySelectorAll('#s3-fields input, #s3-fields select').forEach(el => el.disabled = false);
        }
    }

    // Cron Logic
    function initCron() {
        const hourSelect = document.getElementById('cron-hour');
        const minSelect = document.getElementById('cron-min');

        // Populate Hours
        for(let i=0; i<24; i++) {
            let val = i.toString();
            let label = i.toString().padStart(2, '0');
            let opt = document.createElement('option');
            opt.value = val;
            opt.innerText = label;
            hourSelect.appendChild(opt);
        }
        // Populate Minutes
        for(let i=0; i<60; i+=5) {
            let val = i.toString();
            let label = i.toString().padStart(2, '0');
            let opt = document.createElement('option');
            opt.value = val;
            opt.innerText = label;
            minSelect.appendChild(opt);
        }

        // Parse initial value
        const initial = document.getElementById('cron_expression').value || '0 0 * * *';
        parseCronToUI(initial);
    }

    function parseCronToUI(expression) {
        const parts = expression.trim().split(/\s+/);
        const typeSelect = document.getElementById('cron-type');
        const hourSelect = document.getElementById('cron-hour');
        const minSelect = document.getElementById('cron-min');
        const customWrapper = document.getElementById('cron-custom-wrapper');
        const timeWrapper = document.getElementById('cron-time-wrapper');

        if (parts.length < 5) {
            typeSelect.value = 'custom';
            toggleCron();
            return;
        }

        const [min, hour, dom, month, dow] = parts;

        // Heuristic detection
        if (min !== '*' && hour === '*' && dom === '*' && month === '*' && dow === '*') {
             // Hourly: "30 * * * *"
             typeSelect.value = 'hourly';
             minSelect.value = parseInt(min) || 0;
        } else if (min !== '*' && hour !== '*' && dom === '*' && month === '*' && dow === '*') {
             // Daily: "0 2 * * *"
             typeSelect.value = 'daily';
             minSelect.value = parseInt(min) || 0;
             hourSelect.value = parseInt(hour) || 0;
        } else if (min !== '*' && hour !== '*' && dom === '*' && month === '*' && dow !== '*') {
             // Weekly: "0 2 * * 1"
             typeSelect.value = 'weekly';
             minSelect.value = parseInt(min) || 0;
             hourSelect.value = parseInt(hour) || 0;
             // Note: We don't support picking the DOW in simple UI yet, defaulting to Sunday/Monday?
             // Just show as Custom if DOW is specific?
             // Or simplifiy: Weekly = Sunday
        } else if (min !== '*' && hour !== '*' && dom !== '*' && month === '*' && dow === '*') {
             // Monthly: "0 2 1 * *"
             typeSelect.value = 'monthly';
             minSelect.value = parseInt(min) || 0;
             hourSelect.value = parseInt(hour) || 0;
        } else {
             typeSelect.value = 'custom';
        }

        toggleCron();
    }

    function toggleCron() {
        const type = document.getElementById('cron-type').value;
        const timeWrapper = document.getElementById('cron-time-wrapper');
        const customWrapper = document.getElementById('cron-custom-wrapper');
        const cronInput = document.getElementById('cron_expression');

        if (type === 'custom') {
            timeWrapper.classList.add('d-none');
            customWrapper.classList.remove('d-none');
            cronInput.readOnly = false;
        } else {
            timeWrapper.classList.remove('d-none');
            customWrapper.classList.add('d-none');
            cronInput.readOnly = true;
            buildCron();
        }
    }

    function buildCron() {
        const type = document.getElementById('cron-type').value;
        const h = document.getElementById('cron-hour').value;
        const m = document.getElementById('cron-min').value;
        const cronInput = document.getElementById('cron_expression');

        if (type === 'hourly') {
            cronInput.value = `${m} * * * *`;
        } else if (type === 'daily') {
            cronInput.value = `${m} ${h} * * *`;
        } else if (type === 'weekly') {
            // Default to Sunday (0)
            cronInput.value = `${m} ${h} * * 0`;
        } else if (type === 'monthly') {
            // Default to 1st of month
            cronInput.value = `${m} ${h} 1 * *`;
        }
    }

    function parseCronInput() {
        // Just allows manual typing in custom mode without overriding logic
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleFields();
        initCron();
    });
</script>
@endsection
