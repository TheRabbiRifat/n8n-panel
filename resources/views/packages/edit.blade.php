@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0">
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
                        <label for="cpu_limit" class="form-label">CPU Limit</label>
                        <input type="number" step="0.1" class="form-control" id="cpu_limit" name="cpu_limit" placeholder="e.g. 0.5 or 1.0" value="{{ old('cpu_limit', $package->cpu_limit) }}">
                        <div class="form-text">Number of CPUs. Leave blank for unlimited.</div>
                    </div>

                    <div class="mb-3">
                        <label for="ram_limit" class="form-label">RAM Limit</label>
                        <input type="text" class="form-control" id="ram_limit" name="ram_limit" placeholder="e.g. 512m or 1g" value="{{ old('ram_limit', $package->ram_limit) }}">
                        <div class="form-text">Memory limit (suffix with b, k, m, g). Leave blank for unlimited.</div>
                    </div>

                    <div class="mb-3">
                        <label for="disk_limit" class="form-label">Disk Limit</label>
                        <input type="text" class="form-control" id="disk_limit" name="disk_limit" placeholder="e.g. 10g" value="{{ old('disk_limit', $package->disk_limit) }}">
                        <div class="form-text">Disk storage limit. Leave blank for unlimited.</div>
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
@endsection
