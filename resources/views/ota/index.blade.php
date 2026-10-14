@extends('layouts.app')

@section('title', 'OTA Jobs')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Mass OTA Jobs</h2>
        <a href="{{ route('ota.jobs.create') }}" class="btn btn-primary">New OTA Job</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($jobs->isEmpty())
                <div class="text-center py-5 text-muted">
                    <p class="mb-0 fw-medium">No OTA jobs yet</p>
                    <p class="small">Upload a device list to get started.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">OTA ID</th>
                                <th>Target Version</th>
                                <th>Force</th>
                                <th>Status</th>
                                <th>Units</th>
                                <th>Created By</th>
                                <th>Created</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobs as $job)
                                @php $c = $counts[$job->id] ?? []; @endphp
                                <tr>
                                    <td class="ps-4 font-monospace">{{ $job->otaid }}</td>
                                    <td>{{ $job->target_version ?? '—' }}</td>
                                    <td>
                                        @if ($job->force)
                                            <span class="badge bg-danger">FORCE</span>
                                        @else
                                            <span class="text-muted">off</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($job->status) {
                                                'sending' => 'bg-primary',
                                                'paused' => 'bg-warning text-dark',
                                                'stopped' => 'bg-secondary',
                                                'completed' => 'bg-success',
                                                default => 'bg-light text-dark border',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ strtoupper($job->status) }}</span>
                                    </td>
                                    <td class="small">
                                        {{ array_sum($c) }} total
                                        @if (($c['SUCCESS'] ?? 0) > 0)
                                            &middot; <span class="text-success">{{ $c['SUCCESS'] }} ok</span>
                                        @endif
                                        @if (($c['FAILED'] ?? 0) > 0)
                                            &middot; <span class="text-danger">{{ $c['FAILED'] }} failed</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $job->created_by_role ?? '—' }}</td>
                                    <td class="small text-muted">{{ optional($job->created_at)->toDateTimeString() }}</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('ota.jobs.show', $job) }}" class="btn btn-sm btn-outline-primary">
                                            {{ $job->status === 'draft' ? 'Configure' : 'View' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $jobs->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
