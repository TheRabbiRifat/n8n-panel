@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header py-3 border-0">
                <h4 class="mb-0 fw-bold">Edit Package</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('packages.update', $package->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Package Name</label>
                        <input type="text" class="form-control" id="name" name="name" required value="{{ old('name', $package->name) }}">
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Package Type</label>
                        <select class="form-select" id="type" name="type" onchange="toggleFields()">
                            <option value="instance" {{ old('type', $package->type) == 'instance' ? 'selected' : '' }}>Instance Package</option>
                            <option value="reseller" {{ old('type', $package->type) == 'reseller' ? 'selected' : '' }}>Reseller Package</option>
                        </select>
                        <div class="form-text">Instance packages are for containers. Reseller packages define account limits.</div>
                    </div>

                    <div class="mb-3">
                        <label for="cpu_limit" class="form-label">CPU Limit</label>
                        <input type="number" step="0.1" class="form-control" id="cpu_limit" name="cpu_limit" placeholder="e.g. 0.5 or 1.0" value="{{ old('cpu_limit', $package->cpu_limit) }}">
                        <div class="form-text">Number of CPUs. Leave blank for unlimited.</div>
                    </div>

                    <div class="mb-3">
                        <label for="ram_limit" class="form-label">RAM Limit (GB)</label>
                        <input type="number" step="0.1" class="form-control" id="ram_limit" name="ram_limit" placeholder="e.g. 1.0 or 0.5" value="{{ old('ram_limit', $package->ram_limit) }}">
                        <div class="form-text">Memory limit in Gigabytes (GB). Leave blank for unlimited.</div>
                    </div>

                    <div class="mb-3" id="disk-group">
                        <label for="disk_limit" class="form-label">Disk Limit (GB)</label>
                        <input type="number" step="0.1" class="form-control" id="disk_limit" name="disk_limit" placeholder="e.g. 10 or 2.5" value="{{ old('disk_limit', $package->disk_limit) }}">
                        <div class="form-text">Disk storage limit in Gigabytes (GB). Required for Instance packages.</div>
                    </div>

                    <div class="mb-3 d-none" id="instance-count-group">
                        <label for="instance_count" class="form-label">Instance Count</label>
                        <input type="number" class="form-control" id="instance_count" name="instance_count" placeholder="e.g. 5, 10" value="{{ old('instance_count', $package->instance_count) }}">
                        <div class="form-text">Maximum number of instances allowed. Required for Reseller packages.</div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('packages.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Update Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleFields() {
        const type = document.getElementById('type').value;
        const diskGroup = document.getElementById('disk-group');
        const countGroup = document.getElementById('instance-count-group');

        if (type === 'reseller') {
            diskGroup.classList.add('d-none');
            countGroup.classList.remove('d-none');
        } else {
            diskGroup.classList.remove('d-none');
            countGroup.classList.add('d-none');
        }
    }
    // Run on load in case of validation errors redirecting back
    document.addEventListener('DOMContentLoaded', toggleFields);
</script>
@endsection
