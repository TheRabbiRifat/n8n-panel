@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">Global Environment</h2>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Global n8n Environment Variables</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    These environment variables will be applied to <strong>all</strong> n8n instances created or updated from now on.
                </div>

                <form action="{{ route('admin.environment.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="environment" class="form-label">Key=Value (One per line)</label>
                        <textarea class="form-control font-monospace" id="environment" name="environment" rows="15" placeholder="DB_HOST=10.0.0.1&#10;N8N_ENCRYPTION_KEY=...&#10;GENERIC_TIMEZONE=UTC">{{ $envContent }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Global Environment</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
