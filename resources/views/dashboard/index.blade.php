@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold text-dark mb-1">Dashboard</h2>
        <p class="text-muted mb-0">Overview of your system and n8n instances.</p>
    </div>
    @if(auth()->user()->hasRole('reseller'))
        <a href="{{ route('instances.create') }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> New Instance
        </a>
    @endif
</div>

@if($systemStats)
<div class="row g-4 mb-4">
    <!-- Card 1: System Resources & Activity -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-activity me-2 text-primary"></i>System Resources</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- CPU -->
                    <div class="col-md-6 border-end-md">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-cpu fs-4 text-secondary me-2"></i>
                            <h6 class="fw-bold text-muted mb-0">CPU Load</h6>
                        </div>
                         <h4 class="mb-3 fw-bold">{{ $systemStats['cpu'] }}</h4>

                         <div class="d-flex justify-content-between text-muted small mt-2">
                             <div>
                                 <span class="d-block fw-bold text-dark">{{ $systemStats['loads']['1'] }}</span>
                                 1 min
                             </div>
                             <div>
                                 <span class="d-block fw-bold text-dark">{{ $systemStats['loads']['5'] }}</span>
                                 5 min
                             </div>
                             <div>
                                 <span class="d-block fw-bold text-dark">{{ $systemStats['loads']['15'] }}</span>
                                 15 min
                             </div>
                         </div>
                    </div>

                    <!-- RAM -->
                    <div class="col-md-6">
                         <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-memory fs-4 text-secondary me-2"></i>
                            <h6 class="fw-bold text-muted mb-0">RAM Usage</h6>
                        </div>
                        <h4 class="mb-1 fw-bold">{{ $systemStats['ram']['percent'] }}%</h4>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $systemStats['ram']['percent'] }}%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">{{ $systemStats['ram']['used'] }}MB / {{ $systemStats['ram']['total'] }}MB</small>
                    </div>

                    <!-- Storage -->
                    <div class="col-12"><hr class="my-0 opacity-10"></div>

                    <div class="col-md-6 border-end-md">
                         <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-hdd fs-4 text-secondary me-2"></i>
                            <h6 class="fw-bold text-muted mb-0">Storage</h6>
                        </div>
                        <h4 class="mb-1 fw-bold">{{ $systemStats['disk']['percent'] }}%</h4>
                         <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: {{ $systemStats['disk']['percent'] }}%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">{{ $systemStats['disk']['used_gb'] }}GB / {{ $systemStats['disk']['total_gb'] }}GB</small>
                    </div>

                    <!-- Uptime & Users -->
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 mb-3">
                                 <h6 class="text-uppercase text-muted small fw-bold mb-1">Uptime</h6>
                                 <span class="fw-bold text-dark">{{ $systemStats['uptime'] }}</span>
                            </div>
                             <div class="col-6 mb-3">
                                 <h6 class="text-uppercase text-muted small fw-bold mb-1">Users</h6>
                                 <span class="fw-bold text-dark">{{ $systemStats['user_count'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Server Information -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
             <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-server me-2 text-success"></i>Server Information</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                        <span class="text-muted"><i class="bi bi-display me-2"></i>Hostname</span>
                        <span class="fw-bold text-dark">{{ $systemStats['hostname'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                         <span class="text-muted"><i class="bi bi-clock me-2"></i>Server Time</span>
                        <span class="fw-bold text-dark">{{ $systemStats['time'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                         <span class="text-muted"><i class="bi bi-ethernet me-2"></i>IP Address(es)</span>
                        <span class="fw-bold text-dark text-end" style="max-width: 200px;">{{ Str::limit($systemStats['ips'], 30) }}</span>
                    </li>
                     <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                         <span class="text-muted"><i class="bi bi-window me-2"></i>Operating System</span>
                        <span class="fw-bold text-dark">{{ $systemStats['os'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0">
                         <span class="text-muted"><i class="bi bi-info-circle me-2"></i>Panel Version</span>
                        <span class="badge bg-light text-dark border">v{{ $systemStats['panel_version'] }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@endif

<!-- Core Services Status (Admin Only) -->
@if(auth()->user()->hasRole('admin'))
<div class="card border-0 shadow-sm mb-5">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>Core Services</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                 <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light h-100">
                     <div class="d-flex align-items-center">
                         <i class="bi bi-database-fill fs-3 text-secondary me-3"></i>
                         <div>
                             <h6 class="mb-0 fw-bold">MySQL Database</h6>
                             <small class="text-muted">System Service</small>
                         </div>
                     </div>
                     <span class="badge {{ $mysqlStatus === 'active' ? 'bg-success' : 'bg-danger' }}">
                         {{ $mysqlStatus === 'active' ? 'Running' : 'Stopped' }}
                     </span>
                 </div>
            </div>
            <div class="col-md-4">
                 <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light h-100">
                     <div class="d-flex align-items-center">
                         <i class="bi bi-signpost-split-fill fs-3 text-secondary me-3"></i>
                         <div>
                             <h6 class="mb-0 fw-bold">Traefik Proxy</h6>
                             <small class="text-muted">Docker Container</small>
                         </div>
                     </div>
                     <span class="badge {{ $traefikStatus === 'active' ? 'bg-success' : 'bg-danger' }}">
                         {{ $traefikStatus === 'active' ? 'Running' : 'Stopped' }}
                     </span>
                 </div>
            </div>
            <div class="col-md-4">
                 <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light h-100">
                     <div class="d-flex align-items-center">
                         <i class="bi bi-box-fill fs-3 text-secondary me-3"></i>
                         <div>
                             <h6 class="mb-0 fw-bold">Docker Engine</h6>
                             <small class="text-muted">System Service</small>
                         </div>
                     </div>
                     <span class="badge bg-success">Running</span>
                 </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-dark mb-0">My n8n Instances</h4>
    <a href="{{ route('instances.create') }}" class="btn btn-success shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Create Instance
    </a>
</div>

@if($containers->isEmpty())
<div class="text-center py-5">
    <div class="mb-3 text-muted">
        <i class="bi bi-box-seam fs-1"></i>
    </div>
    <h5 class="text-muted">No n8n instances found</h5>
    <p class="text-muted mb-4">Get started by creating your first n8n instance.</p>
    <a href="{{ route('instances.create') }}" class="btn btn-primary">Create Instance</a>
</div>
@else
<div class="row g-4">
    @foreach($containers as $container)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm container-card">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">{{ $container['name'] }}</h5>
                    <small class="text-muted">ID: {{ substr($container['docker_id'], 0, 12) }}</small>
                </div>
                @if(str_contains($container['status'], 'Up'))
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill d-flex align-items-center gap-1">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> Running
                    </span>
                @else
                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill d-flex align-items-center gap-1">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> Stopped
                    </span>
                @endif
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3 text-muted">
                    <i class="bi bi-hdd-network me-2"></i>
                    <span>Port: <span class="fw-medium text-dark">{{ $container['docker_id'] ? ($container['port'] ?? 'N/A') : 'N/A' }}</span> (Host)</span>
                </div>
                <div class="d-flex align-items-center text-muted">
                    <i class="bi bi-disc me-2"></i>
                    <span class="text-truncate" style="max-width: 200px;">{{ $container['image'] }}</span>
                </div>
                <hr class="my-3 opacity-10">
                <div class="d-flex gap-2">
                     <a href="{{ route('containers.show', $container['id']) }}" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-gear-fill"></i> Manage
                     </a>

                     @if(str_contains($container['status'], 'Up'))
                        <form action="{{ route('containers.stop', $container['id']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Stop">
                                <i class="bi bi-stop-fill"></i>
                            </button>
                        </form>
                    @else
                        <form action="{{ route('containers.start', $container['id']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm" title="Start">
                                <i class="bi bi-play-fill"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
