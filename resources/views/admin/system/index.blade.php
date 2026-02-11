@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold">System Settings</h3>
    <p class="text-secondary">Configure server hostname and manage system services.</p>
</div>

<div class="row g-4">
    <!-- Hostname -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Hostname Configuration</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.system.hostname') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Server Hostname</label>
                        <input type="text" class="form-control" name="hostname" value="{{ $hostname }}" required>
                        <div class="form-text">Changing hostname may require a reboot.</div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Update Hostname</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Power Actions -->
    <div class="col-md-6">
        <div class="card h-100 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Power Options</h5>
            </div>
            <div class="card-body">
                <p>Reboot the entire server. This will stop all services and containers temporarily.</p>
                <form action="{{ route('admin.system.reboot') }}" method="POST" onsubmit="return confirm('Are you sure you want to REBOOT the server?');">
                    @csrf
                    <button class="btn btn-danger w-100 py-2"><i class="bi bi-power"></i> Reboot Server</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Services -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Service Management</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($serviceStatus as $service => $status)
                            <tr>
                                <td class="fw-bold">{{ ucfirst($service) }}</td>
                                <td>
                                    @if($status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">{{ ucfirst($status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('admin.system.service.restart', $service) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-clockwise"></i> Restart</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
