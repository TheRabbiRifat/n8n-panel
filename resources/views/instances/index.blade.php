@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">n8n Instances</h2>
    <a href="{{ route('instances.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Instance</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Resources</th>
                        @role('admin')
                        <th>Owner</th>
                        @endrole
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($instances as $instance)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $instance->name }}</div>
                            <small class="text-muted">{{ substr($instance->docker_id, 0, 12) }}</small>
                        </td>
                        <td>
                            @if($instance->domain)
                                <a href="https://{{ $instance->domain }}" target="_blank">{{ $instance->domain }} <i class="bi bi-box-arrow-up-right small"></i></a>
                            @else
                                <span class="text-muted">No Domain</span>
                            @endif
                        </td>
                        <td>
                            @if(isset($instance->docker_state) && str_contains($instance->docker_state, 'running'))
                                <span class="badge bg-success">Running</span>
                            @else
                                <span class="badge bg-danger">Stopped</span>
                            @endif
                        </td>
                        <td>
                            <small class="d-block">CPU: {{ $instance->package->cpu_limit ?? 'Unl' }}</small>
                            <small class="d-block">RAM: {{ $instance->package->ram_limit ?? 'Unl' }}</small>
                        </td>
                        @role('admin')
                        <td>{{ $instance->user->name }}</td>
                        @endrole
                        <td class="text-end">
                            <a href="{{ route('containers.show', $instance->id) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-gear"></i> Manage
                            </a>
                            <form action="{{ route('instances.destroy', $instance->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this instance?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No instances found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
