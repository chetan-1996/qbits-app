@extends('layouts.app')

@section('title', 'New OTA Job')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-4">
                <h3 class="fw-bold mb-0">New Mass OTA Job</h3>
            </div>

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white fw-semibold py-3">
                    1. Upload Device List
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">
                        Upload an <code>.xlsx</code> or <code>.csv</code> file. The header row names the
                        columns in any order: <strong>IMEI</strong> (required), <strong>Site</strong> and
                        <strong>Note</strong> (optional). Maximum {{ $maxUnits }} units per job.
                    </p>
                    <p>
                        <a href="{{ route('ota.template') }}" class="btn btn-outline-secondary btn-sm">
                            Download CSV template
                        </a>
                    </p>
                    <form method="POST" action="{{ route('ota.jobs.preview') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary px-4">Upload &amp; Validate</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4 text-muted small">
                    <strong>What happens next:</strong> the portal validates every IMEI (15 digits + Luhn
                    check digit), flags duplicates and shows each unit's current firmware version and
                    last-seen time before anything is sent.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
