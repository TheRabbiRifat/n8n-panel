@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold text-dark mb-1">Dashboard</h2>
        <p class="text-muted mb-0">Overview of your system and containers.</p>
    </div>
    @if(auth()->user()->hasRole('reseller'))
        <a href="{{ route('containers.create') }}" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> New Container
        </a>
    @endif
</div>

@if($systemStats)
<div class="row g-4 mb-5">
    <!-- Docker Status -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary me-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-box-seam fs-2"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Docker Service</h6>
                    <h4 class="mb-0 fw-bold {{ $systemStats['docker'] === 'Running' ? 'text-success' : 'text-danger' }}">
                        {{ $systemStats['docker'] }}
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Uptime -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success me-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-clock-history fs-2"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Uptime</h6>
                    <h5 class="mb-0 fw-bold text-dark">{{ $systemStats['uptime'] }}</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- CPU & RAM -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning me-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-cpu fs-2"></i>
                </div>
                <div class="w-100">
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Load / RAM</h6>
                    <div class="d-flex justify-content-between align-items-end">
                        <span class="fw-bold">{{ $systemStats['cpu'] }}</span>
                        <span class="text-muted small">CPU</span>
                    </div>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $systemStats['ram']['percent'] }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted">{{ $systemStats['ram']['percent'] }}%</span>
                        <span class="small text-muted">RAM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disk Usage -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info me-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-hdd fs-2"></i>
                </div>
                <div class="w-100">
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Disk Usage</h6>
                    <h4 class="mb-0 fw-bold text-dark">{{ $systemStats['disk']['percent'] }}%</h4>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $systemStats['disk']['percent'] }}%"></div>
                    </div>
                    <small class="text-muted mt-1 d-block">{{ $systemStats['disk']['used_gb'] }}GB / {{ $systemStats['disk']['total_gb'] }}GB</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-5">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0">System Services</h5>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-light">
                    <i class="bi bi-server fs-4 text-dark"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Nginx Web Server</h6>
                    <span class="badge {{ $nginxStatus === 'active' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $nginxStatus === 'active' ? 'Running' : 'Stopped' }}
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                @if($nginxStatus === 'active')
                    <form action="{{ route('services.handle', ['service' => 'nginx', 'action' => 'stop']) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-stop-fill"></i> Stop
                        </button>
                    </form>
                    <form action="{{ route('services.handle', ['service' => 'nginx', 'action' => 'restart']) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-arrow-clockwise"></i> Restart
                        </button>
                    </form>
                @else
                    <form action="{{ route('services.handle', ['service' => 'nginx', 'action' => 'start']) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-play-fill"></i> Start
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-dark mb-0">My Containers</h4>
    <a href="{{ route('containers.create') }}" class="btn btn-success shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Create Container
    </a>
</div>

@if($containers->isEmpty())
<div class="text-center py-5">
    <div class="mb-3 text-muted">
        <i class="bi bi-box-seam fs-1"></i>
    </div>
    <h5 class="text-muted">No containers found</h5>
    <p class="text-muted mb-4">Get started by creating your first n8n container.</p>
    <a href="{{ route('containers.create') }}" class="btn btn-primary">Create Container</a>
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
                    <!-- Note: I should probably pass port from controller correctly if not in docker info -->
                </div>
                <div class="d-flex align-items-center text-muted">
                    <i class="bi bi-disc me-2"></i>
                    <span class="text-truncate" style="max-width: 200px;">{{ $container['image'] }}</span>
                </div>
                <hr class="my-3 opacity-10">
                <div class="d-flex justify-content-between">
                     @if(str_contains($container['status'], 'Up'))
                        <form action="{{ route('containers.stop', $container['id']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1">
                                <i class="bi bi-stop-fill"></i> Stop
                            </button>
                        </form>
                    @else
                        <form action="{{ route('containers.start', $container['id']) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
                                <i class="bi bi-play-fill"></i> Start
                            </button>
                        </form>
                    @endif

                    <form action="{{ route('containers.destroy', $container['id']) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" onclick="return confirm('Are you sure you want to delete this container?')">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
