@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">Create New Container</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('containers.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Container Name</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. n8n-customer-1" value="{{ old('name') }}">
                        <div class="form-text">Use only letters, numbers, and dashes.</div>
                    </div>

                    <div class="mb-3">
                        <label for="version" class="form-label">n8n Version</label>
                        <select class="form-select" id="version" name="version" required>
                            @foreach($versions as $version)
                                <option value="{{ $version }}" {{ old('version') == $version ? 'selected' : '' }}>{{ $version }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Select preferred n8n version.</div>
                    </div>

                    <div class="mb-3">
                        <label for="port" class="form-label">Exposed Port (Host)</label>
                        <input type="number" class="form-control" id="port" name="port" required placeholder="e.g. 5678" value="{{ old('port') }}">
                        <div class="form-text">The port on the server that maps to n8n's internal port.</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">Create Container</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
