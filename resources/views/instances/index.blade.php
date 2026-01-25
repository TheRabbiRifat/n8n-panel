@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">n8n Instances</h3>
        <p class="text-secondary mb-0">Manage your n8n containers.</p>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2">
        <form action="{{ route('instances.index') }}" method="GET" class="d-flex flex-grow-1">
            <div class="input-group">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search instances..." value="{{ request('search') }}">
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('instances.index') }}" class="btn btn-outline-danger btn-sm" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>

        <a href="{{ route('instances.create') }}" class="btn btn-primary btn-sm shadow-sm text-nowrap w-100 d-grid d-sm-block">
            <i class="bi bi-plus-lg me-1"></i> Create Instance
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped">
                <thead class="bg-light">
                    <tr class="text-secondary text-uppercase small border-bottom">
                        <th class="ps-4 py-3">Name</th>
                        <th class="py-3">Domain</th>
                        <th class="py-3">Database</th>
                        <th class="py-3">Version</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Resources</th>
                        @role('admin')
                        <th class="py-3">Owner</th>
                        @endrole
                        <th class="py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($instances as $instance)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold text-dark">{{ $instance->name }}</div>
                            <div class="small text-muted font-monospace">{{ substr($instance->docker_id, 0, 12) }}</div>
                        </td>
                        <td>
                            @if($instance->domain)
                                <a href="https://{{ $instance->domain }}" target="_blank" class="text-decoration-none fw-medium">{{ $instance->domain }} <i class="bi bi-box-arrow-up-right small ms-1 text-muted"></i></a>
                            @else
                                <span class="badge bg-light text-secondary border">No Domain</span>
                            @endif
                        </td>
                        <td>
                            @if($instance->db_database)
                                <span class="text-secondary small font-monospace"><i class="bi bi-database me-1"></i> {{ $instance->db_database }}</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">SQLite / Internal</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info">{{ $instance->image_tag }}</span>
                        </td>
                        <td>
                            @if(isset($instance->docker_state) && str_contains($instance->docker_state, 'running'))
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Running</span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1"><i class="bi bi-stop-circle-fill me-1"></i> Stopped</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column small">
                                <span class="text-muted">CPU: <span class="fw-bold text-dark">{{ $instance->package->cpu_limit ?? 'Unl' }}</span></span>
                                <span class="text-muted">RAM: <span class="fw-bold text-dark">{{ $instance->package->ram_limit ? $instance->package->ram_limit . ' GB' : 'Unl' }}</span></span>
                            </div>
                        </td>
                        @role('admin')
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-light rounded-circle me-2 d-flex justify-content-center align-items-center text-secondary small" style="width: 24px; height: 24px;">
                                    {{ substr($instance->user->name, 0, 1) }}
                                </div>
                                <span class="small">{{ $instance->user->name }}</span>
                            </div>
                        </td>
                        @endrole
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <a href="{{ route('containers.show', $instance->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-gear-fill"></i> Manage
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form action="{{ route('instances.destroy', $instance->id) }}" method="POST" onsubmit="return confirm('Delete this instance permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->hasRole('admin') ? 7 : 6 }}" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-3 opacity-50"></i>
                                <h5 class="fw-normal">No instances found</h5>
                                <p class="small mb-0">Create your first n8n instance to get started.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top p-3">
        {{ $instances->links() }}
    </div>
</div>
@endsection
