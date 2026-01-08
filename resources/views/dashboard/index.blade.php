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
<div class="row g-4 mb-4">
    <!-- Server Info & Status -->
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary me-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-hdd-network fs-3"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Server IP</h6>
                    <h5 class="mb-0 fw-bold text-dark fs-6">{{ Str::limit($systemStats['ips'], 20) }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success me-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-clock-history fs-3"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Uptime</h6>
                    <h5 class="mb-0 fw-bold text-dark">{{ $systemStats['uptime'] }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info me-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-people fs-3"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Users</h6>
                    <h3 class="mb-0 fw-bold text-dark">{{ $systemStats['user_count'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center bg-indigo bg-opacity-10 text-indigo me-3" style="width: 56px; height: 56px; background-color: #e0e7ff; color: #4338ca;">
                    <i class="bi bi-boxes fs-3"></i>
                </div>
                <div>
                    <h6 class="text-uppercase text-muted small fw-bold mb-1">Containers</h6>
                    <h3 class="mb-0 fw-bold text-dark">{{ $systemStats['container_count'] }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Resources -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cpu text-warning"></i>
                        <h6 class="fw-bold mb-0">CPU Load</h6>
                    </div>
                    <span class="fw-bold fs-5">{{ $systemStats['cpu'] }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-memory text-danger"></i>
                        <h6 class="fw-bold mb-0">RAM Usage</h6>
                    </div>
                    <span class="fw-bold fs-5">{{ $systemStats['ram']['percent'] }}%</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $systemStats['ram']['percent'] }}%"></div>
                </div>
                <small class="text-muted mt-2 d-block text-end">{{ $systemStats['ram']['used'] }}MB / {{ $systemStats['ram']['total'] }}MB</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-hdd text-primary"></i>
                        <h6 class="fw-bold mb-0">Disk Storage</h6>
                    </div>
                    <span class="fw-bold fs-5">{{ $systemStats['disk']['percent'] }}%</span>
                </div>
                <div class="progress mt-4" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $systemStats['disk']['percent'] }}%"></div>
                </div>
                <small class="text-muted mt-2 d-block text-end">{{ $systemStats['disk']['used_gb'] }}GB / {{ $systemStats['disk']['total_gb'] }}GB</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
         <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-server text-secondary"></i>
                        <h6 class="fw-bold mb-0">Services</h6>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <span class="text-muted">Docker</span>
                    <span class="badge {{ $systemStats['docker'] === 'Running' ? 'bg-success' : 'bg-danger' }}">
                        {{ $systemStats['docker'] }}
                    </span>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Nginx</span>
                     <div class="d-flex align-items-center gap-2">
                         <span class="badge {{ $nginxStatus === 'active' ? 'bg-success' : 'bg-secondary' }}">
                            {{ $nginxStatus === 'active' ? 'Running' : 'Stopped' }}
                        </span>
                         @if($nginxStatus === 'active')
                            <form action="{{ route('services.handle', ['service' => 'nginx', 'action' => 'restart']) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-link p-0 text-decoration-none" title="Restart Nginx">
                                    <i class="bi bi-arrow-clockwise text-warning"></i>
                                </button>
                            </form>
                        @else
                             <form action="{{ route('services.handle', ['service' => 'nginx', 'action' => 'start']) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-link p-0 text-decoration-none" title="Start Nginx">
                                    <i class="bi bi-play-fill text-success"></i>
                                </button>
                            </form>
                        @endif
                     </div>
                </div>
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
