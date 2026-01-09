@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">API Tokens</h2>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">

        <!-- Create Token -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Create API Token</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('api-tokens.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="token_name" class="form-label">Token Name</label>
                        <input type="text" class="form-control" name="token_name" placeholder="e.g. CI/CD Pipeline" required>
                    </div>
                    <div class="mb-3">
                        <label for="allowed_ips" class="form-label">Allowed IPs <small class="text-muted">(Optional, Max 5)</small></label>
                        <input type="text" class="form-control" name="allowed_ips" placeholder="192.168.1.1, 10.0.0.1">
                        <div class="form-text">Comma separated IPv4 or IPv6 addresses. Leave blank to allow all.</div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Create Token</button>
                    </div>
                </form>
            </div>
        </div>

        @if (session('flash_token'))
        <div class="alert alert-success d-flex align-items-start" role="alert">
            <i class="bi bi-check-circle-fill fs-4 me-2"></i>
            <div>
                <h5 class="alert-heading">Token Created!</h5>
                <p>Please copy your new API token. For your security, it won't be shown again.</p>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace bg-white" value="{{ session('flash_token') }}" readonly onclick="this.select()">
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ session('flash_token') }}')"><i class="bi bi-clipboard"></i> Copy</button>
                </div>
            </div>
        </div>
        @endif

        <!-- List Tokens -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Active Tokens</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Name</th>
                                <th>Allowed IPs</th>
                                <th>Last Used</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tokens as $token)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $token->name }}</td>
                                <td>
                                    @if(!empty($token->allowed_ips))
                                        @foreach($token->allowed_ips as $ip)
                                            <span class="badge bg-light text-dark border">{{ $ip }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">Any</span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    @if ($token->last_used_at)
                                        {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        Never
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editToken{{ $token->id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('api-tokens.destroy', $token->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Revoke this token?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editToken{{ $token->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('api-tokens.update', $token->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Token: {{ $token->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Allowed IPs</label>
                                                    <input type="text" class="form-control" name="allowed_ips" value="{{ $token->allowed_ips ? implode(', ', $token->allowed_ips) : '' }}">
                                                    <div class="form-text">Comma separated. Max 5. Leave empty to allow all.</div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No active API tokens.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
