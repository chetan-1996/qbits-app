
@extends('layouts.app')

@section('title', 'Telemetry History (Filtered)')

@section('content')
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <h2>Telemetry History</h2>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">IMEI</label>
                    <input
                        type="text"
                        name="collector_id"
                        class="form-control"
                        placeholder="Filter by IMEI"
                        value="{{ request('collector_id') }}"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">From Date</label>
                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="{{ request('date_from') }}"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To Date</label>
                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="{{ request('date_to') }}"
                    >
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="{{ route('telemetry.history.filtered') }}" class="btn btn-secondary">Clear</a>
                    <a href="{{ route('telemetry.history.filtered.export', request()->query()) }}" class="btn btn-success">
                        &#8595; Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($telemetry && $telemetry->count() > 0)
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Created At</th>
                        <th>IMEI</th>
                        <th>VD</th>
                        <th>DATE</th>
                        <th>LOAD</th>
                        <th>CMKEY</th>
                        <th>INDEX</th>
                        <th>MSGID</th>
                        <th>PMKEY</th>
                        <th>ASN_31</th>
                        <th>FW_VER</th>
                        <th>MAXINDEX</th>
                        <th>TIMESTAMP</th>
                        <th>STINTERVAL</th>
                        <th>I</th>
                        <th>PF</th>
                        <th>VN</th>
                        <th>FT1</th>
                        <th>FT2</th>
                        <th>FT3</th>
                        <th>FT4</th>
                        <th>FT5</th>
                        <th>IST</th>
                        <th>LON</th>
                        <th>POW</th>
                        <th>TON</th>
                        <th>APOW</th>
                        <th>BPHI</th>
                        <th>BPHV</th>
                        <th>DCI1</th>
                        <th>DCV1</th>
                        <th>FREQ</th>
                        <th>LKWH</th>
                        <th>POWB</th>
                        <th>POWR</th>
                        <th>POWY</th>
                        <th>RPHI</th>
                        <th>RPHV</th>
                        <th>RPOW</th>
                        <th>TEMP</th>
                        <th>TKWH</th>
                        <th>YPHI</th>
                        <th>YPHV</th>
                        <th>DCKW1</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($telemetry as $item)
                        @php $p = $item->payload ?? []; @endphp
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->created_at->format('Y-m-d H:i:s') }}</td>
                            <td><span class="badge bg-info">{{ $item->collector_id }}</span></td>
                            <td>{{ $p['VD'] ?? '-' }}</td>
                            <td>{{ $p['DATE'] ?? '-' }}</td>
                            <td>{{ $p['LOAD'] ?? '-' }}</td>
                            <td>{{ $p['CMKEY'] ?? '-' }}</td>
                            <td>{{ $p['INDEX'] ?? '-' }}</td>
                            <td>{{ $p['MSGID'] ?? '-' }}</td>
                            <td>{{ $p['PMKEY'] ?? '-' }}</td>
                            <td>{{ $p['ASN_31'] ?? '-' }}</td>
                            <td>{{ $p['FW_VER'] ?? '-' }}</td>
                            <td>{{ $p['MAXINDEX'] ?? '-' }}</td>
                            <td>{{ $p['TIMESTAMP'] ?? '-' }}</td>
                            <td>{{ $p['STINTERVAL'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---I'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---PF'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---VN'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FT1'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FT2'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FT3'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FT4'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FT5'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---IST'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---LON'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---POW'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---TON'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---APOW'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---BPHI'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---BPHV'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---DCI1'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---DCV1'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---FREQ'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---LKWH'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---POWB'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---POWR'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---POWY'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---RPHI'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---RPHV'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---RPOW'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---TEMP'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---TKWH'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---YPHI'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---YPHV'] ?? '-' }}</td>
                            <td>{{ $p['IS-1-0---DCKW1'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-4">
            {{ $telemetry->appends(request()->query())->render() }}
        </div>
    @else
        <div class="alert alert-info" role="alert">
            <strong>No telemetry data found.</strong>
            @if(request('collector_id') || request('date_from') || request('date_to'))
                Try different filter values or <a href="{{ route('telemetry.history.filtered') }}">clear all filters</a>.
            @else
                Start collecting telemetry data to see results here.
            @endif
        </div>
    @endif
</div>

<style>
    .table-responsive {
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
    }
</style>
@endsection
