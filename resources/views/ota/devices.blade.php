@extends('layouts.app')

@section('title', 'OTA Devices')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Dongles &mdash; current firmware</h3>
        <form method="GET" action="{{ route('ota.devices') }}" class="d-flex gap-2">
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm"
                   placeholder="Search IMEI or Dongle ID" style="width: 220px;">
            <button class="btn btn-outline-primary btn-sm">Search</button>
        </form>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">IMEI</th>
                        <th>Dongle ID</th>
                        <th>Current Version (---FWVER)</th>
                        <th>Last Seen</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($states as $st)
                        <tr>
                            <td class="ps-3 font-monospace">{{ $st->imei }}</td>
                            <td class="font-monospace">
                                @if (isset($dongleIds[$st->imei]))
                                    {{ $dongleIds[$st->imei] }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="font-monospace">{{ $st->fw_ver ?? '—' }}</td>
                            <td class="small text-muted">{{ optional($st->last_seen_at)->toDateTimeString() ?? 'never' }}</td>
                            <td>
                                @if ($st->last_seen_at === null)
                                    <span class="badge bg-light text-dark border">never seen</span>
                                @elseif ($st->last_seen_at->lt($offlineAfter))
                                    <span class="badge bg-secondary">offline</span>
                                @else
                                    <span class="badge bg-success">online</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            No dongles seen yet{{ $q !== '' ? ' matching this search' : '' }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $states->links() }}
</div>
@endsection
