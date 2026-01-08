@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="h3">Dashboard</h2>
        <p class="text-muted">Welcome, {{ auth()->user()->name }} <span class="badge bg-info">{{ auth()->user()->roles->pluck('name')->implode(', ') }}</span></p>
    </div>
</div>

@if($systemStats)
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header">Docker Status</div>
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-box-seam"></i> {{ $systemStats['docker'] }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-secondary mb-3">
            <div class="card-header">Uptime</div>
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-clock"></i> {{ $systemStats['uptime'] }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card mb-3">
            <div class="card-header">CPU Load</div>
            <div class="card-body text-center">
                <h5>{{ $systemStats['cpu'] }}</h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card mb-3">
            <div class="card-header">RAM</div>
            <div class="card-body text-center">
                <h5>{{ $systemStats['ram']['percent'] }}%</h5>
                <small>{{ $systemStats['ram']['used'] }} / {{ $systemStats['ram']['total'] }} MB</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card mb-3">
            <div class="card-header">Disk</div>
            <div class="card-body text-center">
                <h5>{{ $systemStats['disk']['percent'] }}%</h5>
                <small>{{ $systemStats['disk']['used_gb'] }} / {{ $systemStats['disk']['total_gb'] }} GB</small>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Containers</h5>
        <a href="{{ route('containers.create') }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Create Container</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Docker ID</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($containers as $container)
                    <tr>
                        <td class="fw-bold">{{ $container['name'] }}</td>
                        <td><code>{{ substr($container['docker_id'], 0, 12) }}</code></td>
                        <td>{{ $container['image'] }}</td>
                        <td>
                            <span class="badge {{ str_contains($container['status'], 'Up') ? 'bg-success' : 'bg-secondary' }}">
                                {{ $container['status'] }}
                            </span>
                        </td>
                        <td>{{ $container['state'] }}</td>
                        <td>
                            @if(str_contains($container['status'], 'Up'))
                                <form action="{{ route('containers.stop', $container['id']) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-stop-fill"></i> Stop</button>
                                </form>
                            @else
                                <form action="{{ route('containers.start', $container['id']) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-play-fill"></i> Start</button>
                                </form>
                            @endif
                            <form action="{{ route('containers.destroy', $container['id']) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i> Remove</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">No containers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
