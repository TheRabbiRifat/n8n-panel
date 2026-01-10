@extends('layouts.app')

@section('content')
<style>
    .whm-panel-icon {
        width: 60px; height: 60px;
        display: flex; align-items: center; justify-content: center;
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 8px;
        font-size: 1.75rem;
        color: var(--whm-sidebar-bg);
        margin-bottom: 1rem;
        transition: all 0.2s;
    }
    .whm-card:hover .whm-panel-icon {
        background: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
    }
    .whm-card {
        text-decoration: none;
        color: inherit;
        display: block;
        height: 100%;
        padding: 1.5rem;
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 4px;
        transition: all 0.2s;
    }
    .whm-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: var(--bs-primary);
    }
</style>

<div class="mb-4">
    <h3 class="fw-bold mb-1">Server Status</h3>
    <p class="text-secondary">Overview of the system and hosted accounts.</p>
</div>

<!-- Top Stats Bar -->
@if($systemStats)
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="card border-top border-4 border-primary h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-secondary mb-1">CPU Load</div>
                <h3 class="fw-bold mb-0">{{ $systemStats['cpu'] }}</h3>
                <div class="small text-muted mt-2">
                    {{ $systemStats['loads']['1'] }} (1m) &bull; {{ $systemStats['loads']['5'] }} (5m)
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-top border-4 border-info h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-secondary mb-1">Memory Usage</div>
                <h3 class="fw-bold mb-0">{{ $systemStats['ram']['percent'] }}%</h3>
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar bg-info" style="width: {{ $systemStats['ram']['percent'] }}%"></div>
                </div>
                <div class="small text-muted mt-2">{{ $systemStats['ram']['used'] }}MB / {{ $systemStats['ram']['total'] }}MB</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-top border-4 border-warning h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-secondary mb-1">Disk Usage</div>
                <h3 class="fw-bold mb-0">{{ $systemStats['disk']['percent'] }}%</h3>
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar bg-warning" style="width: {{ $systemStats['disk']['percent'] }}%"></div>
                </div>
                <div class="small text-muted mt-2">{{ $systemStats['disk']['used_gb'] }}GB / {{ $systemStats['disk']['total_gb'] }}GB</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-top border-4 border-success h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-secondary mb-1">Service Status</div>
                <div class="d-flex flex-column gap-1 mt-2">
                    <div class="d-flex justify-content-between small">
                        <span>MySQL</span>
                        <span class="badge {{ $mysqlStatus === 'active' ? 'bg-success' : 'bg-danger' }}">
                             {{ $mysqlStatus === 'active' ? 'UP' : 'DOWN' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Nginx</span>
                        <span class="badge {{ $nginxStatus === 'active' ? 'bg-success' : 'bg-danger' }}">
                             {{ $nginxStatus === 'active' ? 'UP' : 'DOWN' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<h4 class="fw-bold mb-4">Common Tasks</h4>
<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('instances.create') }}" class="whm-card">
            <div class="whm-panel-icon"><i class="bi bi-plus-circle"></i></div>
            <h5 class="fw-bold">Create a New Account</h5>
            <p class="small text-secondary mb-0">Provision a new n8n instance on the server.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('instances.index') }}" class="whm-card">
            <div class="whm-panel-icon"><i class="bi bi-list-ul"></i></div>
            <h5 class="fw-bold">List Accounts</h5>
            <p class="small text-secondary mb-0">View and manage all hosted instances.</p>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('packages.index') }}" class="whm-card">
            <div class="whm-panel-icon"><i class="bi bi-box-seam"></i></div>
            <h5 class="fw-bold">Feature Manager</h5>
            <p class="small text-secondary mb-0">Define resource packages and limits.</p>
        </a>
    </div>
    @role('admin')
    <div class="col-md-6 col-lg-3">
        <a href="{{ route('admin.settings.index') }}" class="whm-card">
            <div class="whm-panel-icon"><i class="bi bi-gear"></i></div>
            <h5 class="fw-bold">Server Configuration</h5>
            <p class="small text-secondary mb-0">Configure basic setup and environment.</p>
        </a>
    </div>
    @endrole
</div>

<h4 class="fw-bold mb-3">Recent Accounts</h4>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="text-secondary text-uppercase small">
                    <th class="ps-4">Domain</th>
                    <th>User</th>
                    <th>Date Setup</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($containers->take(5) as $container)
                <tr>
                    <td class="ps-4 fw-bold">
                        @if(isset($container['domain']) && $container['domain'])
                            <a href="https://{{ $container['domain'] }}" target="_blank" class="text-decoration-none">{{ $container['domain'] }}</a>
                        @else
                            {{ $container['name'] }}
                        @endif
                    </td>
                    <td>{{ $container['user'] ?? 'System' }}</td>
                    <td>{{ \Carbon\Carbon::parse($container['created_at'] ?? now())->format('M d, Y') }}</td>
                    <td>{{ $container['package'] ?? 'Default' }}</td>
                    <td>
                        @if(str_contains($container['status'], 'Up'))
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active</span>
                        @else
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Suspended</span>
                        @endif
                    </td>
                    <td class="text-end pe-4">
                        <a href="{{ route('containers.show', $container['id']) }}" class="btn btn-sm btn-outline-secondary">Manage</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent border-top p-3 text-center">
        <a href="{{ route('instances.index') }}" class="text-decoration-none fw-bold small">View All Accounts &rarr;</a>
    </div>
</div>
@endsection
