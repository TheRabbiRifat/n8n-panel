@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Checking Requirements</h1>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Server Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Hostname (Domain):</strong> {{ $checks['hostname'] }}
                </div>
                <div class="col-md-6">
                    <strong>Server Public IP:</strong> {{ $checks['server_ip'] }}
                </div>
            </div>
        </div>
    </div>

    @if($checks['is_ip'])
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> You are accessing the panel via an IP address. Please access via a domain name to perform DNS checks.
        </div>
    @else
        <div class="row">
            <!-- A Record Check -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>A Record Check ({{ $checks['hostname'] }})</span>
                        @if($checks['a_record_match'])
                            <span class="badge bg-success">Pass</span>
                        @else
                            <span class="badge bg-danger">Fail</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Should point to: <strong>{{ $checks['server_ip'] }}</strong>
                        </p>
                        <p class="card-text">
                            Currently points to:
                            @if(count($checks['a_records']) > 0)
                                @foreach($checks['a_records'] as $ip)
                                    <span class="badge bg-secondary">{{ $ip }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">No records found</span>
                            @endif
                        </p>
                        @if(!$checks['a_record_match'])
                            <div class="alert alert-danger mb-0 mt-3">
                                <i class="bi bi-x-circle me-2"></i> The domain does not point to this server's IP address. Please update your DNS records.
                            </div>
                        @else
                             <div class="alert alert-success mb-0 mt-3">
                                <i class="bi bi-check-circle me-2"></i> The domain correctly points to this server.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Wildcard Check -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Wildcard Check (*.{{ $checks['hostname'] }})</span>
                        @if($checks['wildcard_match'])
                            <span class="badge bg-success">Pass</span>
                        @else
                            <span class="badge bg-danger">Fail</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Should point to: <strong>{{ $checks['server_ip'] }}</strong>
                        </p>
                        <p class="card-text">
                            Currently points to:
                            @if(count($checks['wildcard_records']) > 0)
                                @foreach($checks['wildcard_records'] as $ip)
                                    <span class="badge bg-secondary">{{ $ip }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">No records found</span>
                            @endif
                        </p>
                         @if(!$checks['wildcard_match'])
                            <div class="alert alert-warning mb-0 mt-3">
                                <i class="bi bi-exclamation-triangle me-2"></i> Wildcard DNS is not configured or incorrect. This is required for subdomain-based instances.
                                <br><small>Suggestion: Add an A record for <code>*</code> pointing to {{ $checks['server_ip'] }}.</small>
                            </div>
                        @else
                             <div class="alert alert-success mb-0 mt-3">
                                <i class="bi bi-check-circle me-2"></i> Wildcard DNS is correctly configured.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Nameservers -->
             <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Nameservers</span>
                        @if($checks['dns_provider'] !== 'Unknown Provider')
                            <span class="badge bg-primary">Hosted by {{ $checks['dns_provider'] }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                         @if(count($checks['nameservers']) > 0)
                            <ul class="list-group list-group-flush">
                                @foreach($checks['nameservers'] as $ns)
                                    <li class="list-group-item">{{ $ns }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted mb-0">No nameservers found.</p>
                        @endif
                        <div class="mt-3 text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Ensure these nameservers are correct at your domain registrar.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
