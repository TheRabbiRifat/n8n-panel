@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-1">API Logs</h3>
    <p class="text-secondary">View history of API requests.</p>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="text-secondary text-uppercase small">
                    <th class="ps-4">Date</th>
                    <th>User</th>
                    <th>Method</th>
                    <th>Endpoint</th>
                    <th>Status</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="ps-4 text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user->name ?? 'Guest/System' }}</td>
                    <td><span class="badge bg-secondary">{{ $log->method }}</span></td>
                    <td class="small font-monospace">{{ $log->endpoint }}</td>
                    <td>
                        @if($log->response_code >= 200 && $log->response_code < 300)
                            <span class="badge bg-success">{{ $log->response_code }}</span>
                        @else
                            <span class="badge bg-danger">{{ $log->response_code }}</span>
                        @endif
                    </td>
                    <td>{{ $log->ip_address }}</td>
                </tr>
                <tr>
                    <td colspan="6" class="p-0">
                        <div class="accordion accordion-flush" id="accordion-{{ $log->id }}">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-1 small text-muted bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $log->id }}">
                                        View Payload Details
                                    </button>
                                </h2>
                                <div id="collapse-{{ $log->id }}" class="accordion-collapse collapse" data-bs-parent="#accordion-{{ $log->id }}">
                                    <div class="accordion-body bg-light">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="small fw-bold">Request</h6>
                                                <pre class="small bg-white p-2 border rounded" style="max-height: 200px; overflow-y: auto;">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="small fw-bold">Response</h6>
                                                <pre class="small bg-white p-2 border rounded" style="max-height: 200px; overflow-y: auto;">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">No logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent border-top p-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection
