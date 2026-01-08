@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">User Management</h2>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add User</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>
                            <div class="fw-bold">{{ $user->name }}</div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="badge bg-info text-dark">{{ ucfirst($role->name) }}</span>
                            @endforeach
                        </td>
                        <td>{{ $user->created_at->format('M d, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
