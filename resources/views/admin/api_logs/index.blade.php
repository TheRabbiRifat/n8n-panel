@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">API Logs</h3>
        <p class="text-secondary mb-0">View history of API requests.</p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('admin.api_logs.index') }}" method="GET" class="d-flex">
            <div class="input-group">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search logs..." value="{{ request('search') }}">
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('admin.api_logs.index') }}" class="btn btn-outline-danger btn-sm" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>

        <form action="{{ route('admin.api_logs.destroy') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL logs? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm shadow-sm text-nowrap">
                <i class="bi bi-trash-fill me-1"></i> Purge All
            </button>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped">
            <thead class="bg-light">
                <tr class="text-secondary text-uppercase small border-bottom">
                    <th class="ps-4 py-3">Date</th>
                    <th class="py-3">User</th>
                    <th class="py-3">Method</th>
                    <th class="py-3">Endpoint</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">IP</th>
                    <th class="py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="ps-4 text-nowrap text-muted small">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle me-2 d-flex justify-content-center align-items-center" style="width: 30px; height: 30px;">
                                <i class="bi bi-person text-secondary"></i>
                            </div>
                            <span class="fw-semibold">{{ $log->user->name ?? 'Guest/System' }}</span>
                        </div>
                    </td>
                    <td>
                        @php
                            $methodColor = match($log->method) {
                                'GET' => 'success',
                                'POST' => 'primary',
                                'PUT' => 'warning',
                                'DELETE' => 'danger',
                                default => 'secondary'
                            };
                        @endphp
                        <span class="badge bg-{{ $methodColor }} bg-opacity-10 text-{{ $methodColor }} border border-{{ $methodColor }}">{{ $log->method }}</span>
                    </td>
                    <td class="small font-monospace text-truncate" style="max-width: 250px;" title="{{ $log->endpoint }}">{{ Str::limit($log->endpoint, 40) }}</td>
                    <td>
                        @if($log->response_code >= 200 && $log->response_code < 300)
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> {{ $log->response_code }}</span>
                        @elseif($log->response_code >= 400 && $log->response_code < 500)
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> {{ $log->response_code }}</span>
                        @else
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> {{ $log->response_code }}</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $log->id }}" aria-expanded="false" title="View Details">
                            <i class="bi bi-eye"></i> Details
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="p-0 border-0">
                        <div class="collapse" id="collapse-{{ $log->id }}">
                            <div class="bg-light p-3 border-bottom shadow-inner">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-arrow-right-short"></i> Request Payload</h6>
                                        <pre class="small bg-white p-3 border rounded text-break shadow-sm" style="max-height: 300px; overflow-y: auto;"><code>{{ !empty($log->request_payload) ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No Payload' }}</code></pre>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-arrow-left-short"></i> Response Payload</h6>
                                        <pre class="small bg-white p-3 border rounded text-break shadow-sm" style="max-height: 300px; overflow-y: auto;"><code>{{ !empty($log->response_payload) ? json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No Response' }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-3 opacity-50"></i>
                            <h5 class="fw-normal">No logs found</h5>
                            <p class="small mb-0">Try adjusting your search criteria or make some API requests.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
        </div>
    </div>
    <div class="card-footer bg-white border-top p-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection
