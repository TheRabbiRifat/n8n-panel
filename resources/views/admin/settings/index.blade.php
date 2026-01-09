@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">Panel Settings</h2>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">General Configuration</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="panel_app_name" class="form-label">Application Name</label>
                        <input type="text" class="form-control" name="panel_app_name" value="{{ $settings['panel_app_name'] ?? 'n8n Panel' }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="panel_footer_text" class="form-label">Footer Text</label>
                        <input type="text" class="form-control" name="panel_footer_text" value="{{ $settings['panel_footer_text'] ?? '' }}" placeholder="© 2026 n8n Control Panel.">
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="panel_registration_enabled" name="panel_registration_enabled" value="1" {{ ($settings['panel_registration_enabled'] ?? 'false') == 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="panel_registration_enabled">Enable User Registration</label>
                        </div>
                        <div class="form-text">If disabled, only admins can create users.</div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
