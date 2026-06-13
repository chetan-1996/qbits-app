@extends('layouts.app')

@section('title', 'Admin Login')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm mt-5">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Admin Access</h5>
                </div>
                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <form method="POST" action="{{ $actionUrl }}">
                        @csrf
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
