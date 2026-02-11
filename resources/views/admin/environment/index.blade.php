@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">Global Environment</h2>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<form action="{{ route('admin.environment.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row">
        <!-- General Env -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">General Variables</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i> Key=Value (One per line). e.g., GENERIC_TIMEZONE=UTC
                    </div>
                    <textarea class="form-control font-monospace" id="environment" name="environment" rows="15" placeholder="DB_HOST=10.0.0.1">{{ $otherEnvContent }}</textarea>
                </div>
            </div>
        </div>

        <!-- SMTP Config -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">SMTP Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border">
                        Details for sending emails from n8n instances.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" name="smtp_host" value="{{ $smtpSettings['N8N_SMTP_HOST'] ?? '' }}" placeholder="smtp.example.com">
                    </div>
                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="smtp_port" value="{{ $smtpSettings['N8N_SMTP_PORT'] ?? '587' }}">
                         </div>
                         <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="smtp_ssl" value="1" id="smtp_ssl" {{ ($smtpSettings['N8N_SMTP_SSL'] ?? 'false') == 'true' ? 'checked' : '' }}>
                                <label class="form-check-label" for="smtp_ssl">Use SSL/TLS</label>
                            </div>
                         </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="smtp_user" value="{{ $smtpSettings['N8N_SMTP_USER'] ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="smtp_pass" value="{{ $smtpSettings['N8N_SMTP_PASS'] ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sender Email</label>
                        <input type="email" class="form-control" name="smtp_sender" value="{{ $smtpSettings['N8N_SMTP_SENDER'] ?? '' }}" placeholder="n8n@example.com">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary btn-lg">Save Global Settings</button>
    </div>
</form>
@endsection
