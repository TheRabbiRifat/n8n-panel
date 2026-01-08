@extends('layouts.app')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Create New Container</h2>
    <form action="{{ route('containers.store') }}" method="POST">
        @csrf
        <label>Container Name</label>
        <input type="text" name="name" required placeholder="e.g. n8n-customer-1">

        <label>Image</label>
        <input type="text" name="image" required value="n8nio/n8n:latest">

        <label>Exposed Port</label>
        <input type="number" name="port" required placeholder="e.g. 5678">

        <button type="submit" class="btn btn-success" style="width:100%; margin-top:10px;">Create Container</button>
    </form>
</div>
@endsection
