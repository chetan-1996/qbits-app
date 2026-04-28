@extends('layouts.app')

@section('title', 'Telemetry Heartbeat History')

@section('content')
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>Telemetry Heartbeat History</h2>
        </div>
        <div class="col-md-4">
            <form method="GET" class="form-inline">
                <input
                    type="text"
                    name="collector_id"
                    class="form-control"
                    placeholder="Filter by IMEI"
                    value="{{ request('collector_id') }}"
                >
                <button type="submit" class="btn btn-primary ml-2">Search</button>
            </form>
        </div>
    </div>

    @if($telemetry && $telemetry->count() > 0)
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th width="50">ID</th>
                        <th>IMEI</th>
                        <th>Payload</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($telemetry as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>
                                <span class="badge bg-success">{{ $item->collector_id }}</span>
                            </td>
                            <td>
                                <button
                                    class="btn btn-sm btn-outline-secondary"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#payload-{{ $item->id }}"
                                >
                                    View
                                </button>
                                <div class="collapse mt-2" id="payload-{{ $item->id }}">
                                    <pre class="bg-light p-3"><code>{{ json_encode($item->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </div>
                            </td>
                            <td>{{ $item->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-4">
            {{ $telemetry->render() }}
        </div>
    @else
        <div class="alert alert-info" role="alert">
            <strong>No heartbeat data found.</strong>
            @if(request('collector_id'))
                Try a different Collector ID or clear the filter.
            @else
                Start collecting heartbeat data to see results here.
            @endif
        </div>
    @endif
</div>

<style>
    pre {
        max-height: 300px;
        overflow-y: auto;
        font-size: 12px;
    }

    .table-responsive {
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
    }
</style>
@endsection
