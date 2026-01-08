@extends('layouts.app')

@section('content')
<div class="card">
    <h2>Dashboard</h2>
    <p>Welcome, {{ auth()->user()->name }} ({{ auth()->user()->roles->pluck('name')->implode(', ') }})</p>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3>Containers</h3>
        <a href="{{ route('containers.create') }}" class="btn btn-success">Create Container</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Docker ID</th>
                <th>Status</th>
                <th>State</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($containers as $container)
            <tr>
                <td>{{ $container['name'] }}</td>
                <td>{{ $container['docker_id'] }}</td>
                <td>{{ $container['status'] }}</td>
                <td>{{ $container['state'] }}</td>
                <td>
                    @if(str_contains($container['status'], 'Up'))
                        <form action="{{ route('containers.stop', $container['id']) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-warning">Stop</button>
                        </form>
                    @else
                        <form action="{{ route('containers.start', $container['id']) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-success">Start</button>
                        </form>
                    @endif
                    <form action="{{ route('containers.destroy', $container['id']) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Remove</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
