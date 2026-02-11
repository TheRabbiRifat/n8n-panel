@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">Instance Discovery</h2>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        @if(count($orphans) > 0)
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Image</th>
                        <th>State</th>
                        <th>Ports</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orphans as $orphan)
                    <tr>
                        <td class="text-monospace small">{{ substr($orphan['id'], 0, 12) }}</td>
                        <td>{{ $orphan['name'] }}</td>
                        <td><span class="badge bg-secondary">{{ $orphan['image'] }}</span></td>
                        <td>
                            @if(str_contains($orphan['status'], 'Up'))
                                <span class="badge bg-success">Running</span>
                            @else
                                <span class="badge bg-danger">Stopped</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $orphan['ports'] }}</td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#importModal"
                                    data-id="{{ $orphan['id'] }}"
                                    data-name="{{ $orphan['name'] }}"
                                    data-ports="{{ $orphan['ports'] }}">
                                <i class="bi bi-box-arrow-in-down"></i> Import
                            </button>
                            <form action="{{ route('containers.deleteOrphan') }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this orphan instance? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="docker_id" value="{{ $orphan['id'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-5 text-center text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <p>No orphan instances found.</p>
        </div>
        @endif
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('containers.import') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Instance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="docker_id" id="modalDockerId">

                    <div class="mb-3">
                        <label for="modalName" class="form-label">Instance Name</label>
                        <input type="text" class="form-control" id="modalName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="modalUser" class="form-label">Assign to User</label>
                        <select class="form-select" id="modalUser" name="user_id" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modalPackage" class="form-label">Assign Package (Required)</label>
                        <select class="form-select" id="modalPackage" name="package_id" required>
                            <option value="">Select Package</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}">{{ $package->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Limits from this package will be applied immediately.</div>
                    </div>

                    <div class="mb-3">
                        <label for="modalPort" class="form-label">Main Port</label>
                        <input type="number" class="form-control" id="modalPort" name="port" required placeholder="e.g. 5678">
                        <div class="form-text">Specify the main application port for the dashboard link.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Instance</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var importModal = document.getElementById('importModal');
        importModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var ports = button.getAttribute('data-ports');

            var modalDockerId = importModal.querySelector('#modalDockerId');
            var modalName = importModal.querySelector('#modalName');

            modalDockerId.value = id;
            modalName.value = name;

            // Try to guess port (simple regex for common patterns like 0.0.0.0:5678->5678/tcp)
            var portMatch = ports.match(/:(\d+)->/);
            if (portMatch && portMatch[1]) {
                 importModal.querySelector('#modalPort').value = portMatch[1];
            }
        });
    });
</script>
@endsection
