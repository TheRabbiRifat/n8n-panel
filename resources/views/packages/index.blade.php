@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold text-dark mb-1">Package Management</h2>
        <p class="text-muted mb-0">Define resource limits for your instances.</p>
    </div>
    @role('admin')
    <a href="{{ route('packages.create') }}" class="btn btn-primary shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Create Package
    </a>
    @endrole
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Type</th>
                        <th>CPU Limit</th>
                        <th>RAM Limit</th>
                        <th>Disk Limit</th>
                        <th>Instances</th>
                        @role('admin')
                        <th>Owner</th>
                        @endrole
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($packages as $package)
                    <tr>
                        <td class="ps-4 fw-bold">{{ $package->name }}</td>
                        <td>
                            @if($package->type == 'reseller')
                                <span class="badge bg-primary">Reseller</span>
                            @else
                                <span class="badge bg-success">Instance</span>
                            @endif
                        </td>
                        <td>{{ $package->cpu_limit ?? 'Unlimited' }}</td>
                        <td>{{ $package->ram_limit ?? 'Unlimited' }}</td>
                        <td>{{ $package->disk_limit ?? 'Unlimited' }}</td>
                        <td>{{ $package->instance_count ?? '-' }}</td>
                        @role('admin')
                        <td>
                            <span class="badge bg-secondary">{{ $package->user->name }}</span>
                        </td>
                        @endrole
                        <td>{{ $package->created_at->format('M d, Y') }}</td>
                        <td class="text-end pe-4">
                            @role('admin')
                            <a href="{{ route('packages.edit', $package->id) }}" class="btn btn-outline-primary btn-sm me-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('packages.destroy', $package->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure? This will not delete instances using this package.')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @else
                                <span class="badge bg-secondary">Read-Only</span>
                            @endrole
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->hasRole('admin') ? 6 : 5 }}" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                            No packages found. Create one to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
