@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">Create New n8n Instance</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('instances.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Instance Name</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. my-workflow-engine" value="{{ old('name') }}">
                        <div class="form-text">Used for subdomain (e.g., name.domain.com). Letters, numbers, and dashes only.</div>
                    </div>

                    <div class="mb-3">
                        <label for="version" class="form-label">n8n Version</label>
                        <select class="form-select" id="version" name="version" required>
                            @foreach($versions as $key => $label)
                                <option value="{{ $key }}" {{ old('version') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="package_id" class="form-label">Resource Package</label>
                        <select class="form-select" id="package_id" name="package_id" required>
                            <option value="" disabled selected>Select a package...</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'selected' : '' }}>
                                    {{ $package->name }} (CPU: {{ $package->cpu_limit ?? 'Unl' }}, RAM: {{ $package->ram_limit ?? 'Unl' }}, Disk: {{ $package->disk_limit ?? 'Unl' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-light border">
                        <i class="bi bi-hdd-fill me-2"></i> <strong>Volume:</strong> A persistent volume will be automatically created at <code>/var/lib/n8n/instances/[name]</code>.
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('instances.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">Create Instance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
