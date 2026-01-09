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
                                <th>Last Used</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tokens as $token)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $token->name }}</td>
                                <td class="text-muted small">
                                    @if ($token->last_used_at)
                                        {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        Never
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <form action="{{ route('api-tokens.destroy', $token->id) }}" method="POST" onsubmit="return confirm('Revoke this token?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No active API tokens.</td>
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
