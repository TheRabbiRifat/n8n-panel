@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Create New User</h2>
    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        <label>Name</label>
        <input type="text" name="name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Role</label>
        <select name="role" required>
            @foreach($roles as $role)
                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-success" style="width:100%; margin-top:10px;">Create User</button>
    </form>
</div>
@endsection
