@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Backup Settings</div>
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
                            <option value="sftp" {{ optional($setting)->driver == 'sftp' ? 'selected' : '' }}>SFTP (SSH)</option>
                            <option value="s3" {{ optional($setting)->driver == 's3' ? 'selected' : '' }}>S3 (AWS/MinIO)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Retention (Days)</label>
                        <input type="number" name="retention_days" class="form-control" value="{{ optional($setting)->retention_days ?? 30 }}" min="1">
                        <div class="form-text">Backups older than this will be automatically deleted.</div>
                    </div>

                    {{-- Cron Schedule removed in favor of manual crontab management --}}

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
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_passive" value="1" id="is_passive" {{ (optional($setting)->is_passive ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_passive">
                                    Passive Mode (Recommended)
                                </label>
                                <div class="form-text">Disable if you are having connection issues and your server requires Active mode. Auto-detected if unchecked during test.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Root Path</label>
                            <input type="text" name="path" class="form-control" value="{{ optional($setting)->path ?? '/' }}">
                        </div>
                    </div>

                    <!-- SFTP Fields -->
                    <div id="sftp-fields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Host</label>
                            <input type="text" name="host" class="form-control" value="{{ optional($setting)->host }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Port</label>
                            <input type="text" name="port" class="form-control" value="{{ optional($setting)->port ?? 22 }}">
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
            <div class="card-header fw-bold">System Cron</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Add this command to your system crontab (`crontab -e`) to schedule backups:</p>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace form-control-sm" value="0 2 * * * cd /var/n8n-panel && /usr/bin/php artisan backup:run >> /var/log/n8n-backup.log 2>&1" readonly id="cron-cmd">
                    <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cron-cmd').value)"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="mt-2">
                    <span class="badge bg-info bg-opacity-10 text-info border border-info"><i class="bi bi-info-circle me-1"></i> Manual Setup Required</span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-bold">Manual Actions</div>
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
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold">Available Backups (Remote)</span>
                    <div>
                        <button type="submit" form="restore-form" class="btn btn-sm btn-primary" onclick="return confirm('Restore selected instances? This will overwrite existing data or create new instances.')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Selected
                        </button>
                        <span class="badge bg-secondary ms-2" id="backup-count">{{ count($backups) }} Found</span>
                    </div>
                </div>
                <input type="text" id="backup-search" class="form-control form-control-sm" placeholder="Search backups..." onkeyup="filterBackups()">
            </div>
            <div class="card-body p-0">
                <form id="restore-form" action="{{ route('admin.backups.restore') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="backups-table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4" style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="select-all" onclick="document.querySelectorAll('.backup-check').forEach(c => c.checked = this.checked)">
                                    </th>
                                    <th>Instance / Folder</th>
                                    <th class="text-center">Count</th>
                                    <th>Last Backup</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($backups as $folder)
                                <tr class="backup-row">
                                    <td class="ps-4">
                                        <input type="checkbox" name="folders[]" value="{{ $folder['name'] }}" class="form-check-input backup-check">
                                    </td>
                                    <td style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#files-{{ Str::slug($folder['name']) }}">
                                        <span class="fw-semibold instance-name">
                                            <i class="bi bi-folder-fill text-warning me-2"></i> {{ $folder['name'] }}
                                        </span>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary rounded-pill">{{ $folder['count'] }}</span></td>
                                    <td class="text-muted small">{{ $folder['last_backup'] }}</td>
                                    <td class="text-end pe-4">
                                        <i class="bi bi-chevron-down text-muted" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#files-{{ Str::slug($folder['name']) }}"></i>
                                    </td>
                                </tr>
                                <tr class="backup-details-row">
                                    <td colspan="5" class="p-0 border-0">
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
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-archive fs-1 d-block opacity-25 mb-2"></i>
                                    No backups found on configured storage.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Driver Toggle
    function toggleFields() {
        const driver = document.getElementById('driver-select').value;
        const ftpFields = document.getElementById('ftp-fields');
        const sftpFields = document.getElementById('sftp-fields');
        const s3Fields = document.getElementById('s3-fields');

        document.querySelectorAll('#ftp-fields input, #ftp-fields select').forEach(el => el.disabled = true);
        document.querySelectorAll('#sftp-fields input, #sftp-fields select').forEach(el => el.disabled = true);
        document.querySelectorAll('#s3-fields input, #s3-fields select').forEach(el => el.disabled = true);

        ftpFields.classList.add('d-none');
        sftpFields.classList.add('d-none');
        s3Fields.classList.add('d-none');

        if (driver === 'ftp') {
            ftpFields.classList.remove('d-none');
            document.querySelectorAll('#ftp-fields input, #ftp-fields select').forEach(el => el.disabled = false);
        } else if (driver === 'sftp') {
            sftpFields.classList.remove('d-none');
            document.querySelectorAll('#sftp-fields input, #sftp-fields select').forEach(el => el.disabled = false);
        } else if (driver === 's3') {
            s3Fields.classList.remove('d-none');
            document.querySelectorAll('#s3-fields input, #s3-fields select').forEach(el => el.disabled = false);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleFields();
    });

    function filterBackups() {
        const input = document.getElementById('backup-search');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('backups-table');
        const rows = table.getElementsByClassName('backup-row');
        const detailsRows = table.getElementsByClassName('backup-details-row');
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            const nameSpan = rows[i].querySelector('.instance-name');
            if (nameSpan) {
                const txtValue = nameSpan.textContent || nameSpan.innerText;
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                    // Keep details row hidden unless expanded, but don't force hide if parent visible
                    // Actually, we should hide/show both as a unit?
                    // No, details row visibility is managed by bootstrap collapse.
                    // However, if we hide the parent row, the details row might remain 'visible' in DOM but detached visually.
                    // Better to hide details row too if parent matches.
                    // But if parent matches, we show parent. Details row is initially hidden anyway.
                    // If parent DOES NOT match, we hide parent. What about details row?
                    // We should probably hide the next sibling (details row) if parent is hidden.
                    if (detailsRows[i]) detailsRows[i].style.display = "";
                    visibleCount++;
                } else {
                    rows[i].style.display = "none";
                    if (detailsRows[i]) detailsRows[i].style.display = "none";
                }
            }
        }

        document.getElementById('backup-count').textContent = visibleCount + ' Found';
    }

    // Fix for JS loop variable mismatch if rows/detailsRows lengths differ (unlikely but safe)
    // The filter loop assumes index parity.
    // Ensure rows match.
    // The logic above is fine as long as row structure is consistent.
</script>
@endsection
