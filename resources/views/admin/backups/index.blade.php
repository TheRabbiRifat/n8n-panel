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
                        <label class="form-label">Cron Schedule</label>
                        <input type="text" name="cron_expression" class="form-control font-monospace" value="{{ optional($setting)->cron_expression ?? '0 2 * * *' }}">
                        <div class="form-text">Example: <code>0 2 * * *</code> (Daily at 2am)</div>
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
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $file)
                            <tr>
                                <td>{{ basename($file) }}</td>
                                <td class="text-end">
                                    <span class="text-muted small">Download via client recommended</span>
                                    <!-- Download/Restore logic requires more complex streaming for huge files,
                                         currently purely informational listing based on driver -->
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">No backups found on configured storage.</td>
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
    function toggleFields() {
        const driver = document.getElementById('driver-select').value;
        const ftpFields = document.getElementById('ftp-fields');
        const s3Fields = document.getElementById('s3-fields');
        const commonUser = document.querySelector('[name="username"]'); // Conflict handling?

        // This simple toggle has a flaw: username/password inputs are duplicated in HTML but share names.
        // We should disable inputs in hidden divs to prevent submitting wrong data.

        // Better: Use JS to show/hide and enable/disable
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
    document.addEventListener('DOMContentLoaded', toggleFields);
</script>
@endsection
