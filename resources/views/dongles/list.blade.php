@extends('layouts.app')

@section('title', 'Dongle List')

@section('styles')
<style>
    .search-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 12px;
    }
    .search-card .card-body {
        padding: 1.5rem;
    }
    .search-input {
        border-radius: 8px;
        border: none;
        padding: 0.75rem 1rem;
    }
    .search-btn {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
    }
    .dongle-table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .dongle-table thead th {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        color: white;
        font-weight: 600;
        padding: 1rem;
        border: none;
    }
    .dongle-table tbody td {
        padding: 0.875rem 1rem;
        vertical-align: middle;
    }
    .dongle-table tbody tr:hover {
        background-color: #f8f9ff;
    }
    .badge-active {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        padding: 0.5em 1em;
        border-radius: 20px;
        font-weight: 500;
    }
    .badge-inactive {
        background: linear-gradient(135deg, #6c757d 0%, #adb5bd 100%);
        padding: 0.5em 1em;
        border-radius: 20px;
        font-weight: 500;
    }
    .pagination-container .pagination {
        gap: 0.25rem;
    }
    .pagination-container .page-link {
        border-radius: 8px;
        border: none;
        padding: 0.5rem 0.875rem;
        color: #667eea;
        font-weight: 500;
    }
    .pagination-container .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .pagination-container .page-item.disabled .page-link {
        background: #e9ecef;
        color: #adb5bd;
    }
    .stats-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .stats-number {
        font-size: 1.75rem;
        font-weight: 700;
        color: #667eea;
    }
</style>
@endsection

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Dongle List</h2>
        <a href="{{ url('/dongles/import') }}" class="btn btn-primary">
            <i class="bi bi-upload"></i> Import Dongles
        </a>
    </div>

    <!-- Search Card -->
    <div class="card search-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('dongles.list') }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="dongle_id" class="form-control search-input" 
                               placeholder="Search Dongle ID..." 
                               value="{{ request('dongle_id') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="imei" class="form-control search-input" 
                               placeholder="Search IMEI..." 
                               value="{{ request('imei') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="sim_num" class="form-control search-input" 
                               placeholder="Search SIM Number..." 
                               value="{{ request('sim_num') }}">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-light search-btn flex-fill">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="{{ route('dongles.list') }}" class="btn btn-outline-light search-btn">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <div class="stats-number">{{ $totalCount ?? $dongles->total() }}</div>
                    <small class="text-muted">Total Dongles</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <div class="stats-number text-success">{{ $activeCount ?? 0 }}</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <div class="stats-number text-secondary">{{ $inactiveCount ?? 0 }}</div>
                    <small class="text-muted">Inactive</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <div class="stats-number text-info">{{ $dongles->count() }}</div>
                    <small class="text-muted">Showing on Page</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card dongle-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Dongle ID</th>
                        <th>IMEI</th>
                        <th>IMSI</th>
                        <th>SIM Number</th>
                        <th style="width: 100px">Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dongles as $dongle)
                        <tr>
                            <td class="text-muted">{{ $dongles->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $dongle->dongle_id }}</strong></td>
                            <td><code class="text-dark">{{ $dongle->imei }}</code></td>
                            <td><code class="text-dark">{{ $dongle->imsi }}</code></td>
                            <td>{{ $dongle->sim_num }}</td>
                            <td>
                                @if ($dongle->status == 1)
                                    <span class="badge badge-active">Active</span>
                                @else
                                    <span class="badge badge-inactive">Inactive</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $dongle->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">No dongles found matching your search.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-container mt-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                Showing {{ $dongles->firstItem() ?? 0 }} to {{ $dongles->lastItem() ?? 0 }} of {{ $dongles->total() }} results
            </div>
            <div>
                {{ $dongles->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
