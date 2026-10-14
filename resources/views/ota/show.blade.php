@extends('layouts.app')

@section('title', 'OTA Job ' . $job->otaid)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Job <span class="font-monospace">{{ $job->otaid }}</span></h3>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge fs-6" id="jobStatusBadge">{{ strtoupper($job->status) }}</span>
            <a href="{{ route('ota.jobs.export', $job) }}" class="btn btn-outline-secondary btn-sm">Export Excel</a>
            @if ($job->status === 'sending')
                <form method="POST" action="{{ route('ota.jobs.pause', $job) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-warning btn-sm">Pause</button>
                </form>
            @endif
            @if ($job->status === 'paused')
                <form method="POST" action="{{ route('ota.jobs.resume', $job) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-primary btn-sm">Resume</button>
                </form>
            @endif
            @if (in_array($job->status, ['sending', 'paused']))
                <form method="POST" action="{{ route('ota.jobs.stop', $job) }}" class="d-inline"
                      onsubmit="return confirm('Stop this job? Nothing further will be sent; units already sent carry on.')">
                    @csrf
                    <button class="btn btn-danger btn-sm">Stop</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-3 text-muted small">
        Target version <span class="font-monospace fw-bold">{{ $job->target_version }}</span>
        &middot; <a href="{{ $job->url }}" target="_blank" rel="noopener">firmware file</a>
        &middot; force {{ $job->force ? 'ON' : 'off' }}
        &middot; gap {{ $job->send_gap_seconds }}s, max {{ $job->max_concurrent }} concurrent
        &middot; created by {{ $job->created_by_role ?? '?' }}
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Counts per state (MO-21) --}}
    <div id="stateCounts" class="d-flex flex-wrap gap-2 mb-3"></div>

    <form method="POST" action="{{ route('ota.jobs.retry', $job) }}" id="retryForm">
        @csrf
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-semibold py-2 d-flex justify-content-between align-items-center">
                <span>Units</span>
                <button type="submit" class="btn btn-outline-light btn-sm" id="retryBtn" disabled>
                    Retry selected
                </button>
            </div>
            <div class="card-body p-0" style="max-height: 560px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3" style="width:32px;"></th>
                            <th>IMEI</th>
                            <th>Site</th>
                            <th>Version (before &rarr; after)</th>
                            <th>State</th>
                            <th>Reason</th>
                            <th>Last Update</th>
                        </tr>
                    </thead>
                    <tbody id="unitsBody"></tbody>
                </table>
            </div>
        </div>
    </form>

    {{-- Audit log (MO-26) --}}
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-light fw-semibold py-2">Audit log</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <tbody>
                    @foreach ($events as $e)
                        <tr>
                            <td class="ps-3 text-muted" style="white-space:nowrap">{{ optional($e->created_at)->toDateTimeString() }}</td>
                            <td>{{ $e->event }}</td>
                            <td class="text-muted small">{{ $e->actor_role }}{{ $e->actor_ip ? ' @ ' . $e->actor_ip : '' }}</td>
                            <td class="text-muted small font-monospace">
                                @if ($e->meta)
                                    {{ json_encode($e->meta) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var rowsUrl = @json(route('ota.jobs.rows', $job));
    var body = document.getElementById('unitsBody');
    var countsBox = document.getElementById('stateCounts');
    var retryBtn = document.getElementById('retryBtn');
    var statusBadge = document.getElementById('jobStatusBadge');

    var badgeClass = {
        QUEUED: 'bg-light text-dark border',
        SENT: 'bg-info text-dark',
        ACCEPTED: 'bg-primary',
        DOWNLOADING: 'bg-warning text-dark',
        SUCCESS: 'bg-success',
        UP_TO_DATE: 'bg-success',
        REJECTED: 'bg-danger',
        FAILED: 'bg-danger',
        ROLLED_BACK: 'bg-danger',
        REPLACED: 'bg-secondary',
        NO_RESPONSE: 'bg-secondary',
        TIMED_OUT: 'bg-secondary',
        INVALID: 'bg-light text-dark border',
        SKIPPED: 'bg-light text-dark border'
    };

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function render(data) {
        statusBadge.textContent = data.status.toUpperCase();
        statusBadge.className = 'badge fs-6 ' + ({
            sending: 'bg-primary', paused: 'bg-warning text-dark',
            stopped: 'bg-secondary', completed: 'bg-success'
        }[data.status] || 'bg-light text-dark border');

        var order = ['QUEUED','SENT','ACCEPTED','DOWNLOADING','SUCCESS','UP_TO_DATE','REJECTED','FAILED','ROLLED_BACK','REPLACED','NO_RESPONSE','TIMED_OUT','INVALID','SKIPPED'];
        countsBox.innerHTML = order.filter(function (s) { return data.counts[s]; }).map(function (s) {
            return '<span class="badge ' + (badgeClass[s] || 'bg-secondary') + ' fs-6">' + s + ': ' + data.counts[s] + '</span>';
        }).join('');

        body.innerHTML = data.units.map(function (u) {
            var flags = '';
            if (u.version_differs) flags += ' <span class="badge bg-warning text-dark">version differs</span>';
            if (u.confirmed_by_telemetry) flags += ' <span class="badge bg-info text-dark">via telemetry</span>';
            if (u.attempt > 1) flags += ' <span class="badge bg-light text-dark border">attempt ' + u.attempt + '</span>';
            var reason = esc(u.reason || '');
            if (u.detail) reason += (reason ? ' — ' : '') + '<span class="text-muted">' + esc(u.detail) + '</span>';
            if (u.unit_ts) reason += '<br><span class="text-muted small">unit ts: ' + esc(u.unit_ts) + '</span>';
            return '<tr>' +
                '<td class="ps-3">' + (u.retryable ? '<input type="checkbox" name="unit_ids[]" value="' + u.id + '" class="form-check-input retry-check">' : '') + '</td>' +
                '<td class="font-monospace">' + esc(u.imei) + '</td>' +
                '<td>' + esc(u.site || '') + '</td>' +
                '<td class="font-monospace">' + esc(u.version_before || '—') +
                    ' <span class="text-muted">&rarr;</span> ' + esc(u.version_after || '—') + '</td>' +
                '<td><span class="badge ' + (badgeClass[u.state] || 'bg-secondary') + '">' + esc(u.state) + '</span>' + flags + '</td>' +
                '<td class="small">' + reason + '</td>' +
                '<td class="small text-muted">' + esc(u.last_update_at || '—') + '</td>' +
                '</tr>';
        }).join('');

        bindRetryChecks();
    }

    function bindRetryChecks() {
        var boxes = body.querySelectorAll('.retry-check');
        var refresh = function () {
            var any = false;
            boxes.forEach(function (b) { if (b.checked) any = true; });
            retryBtn.disabled = !any;
        };
        boxes.forEach(function (b) { b.onchange = refresh; });
        // restore previous selections
        var prev = window._retrySel || [];
        boxes.forEach(function (b) {
            if (prev.indexOf(b.value) !== -1) { b.checked = true; }
            b.onchange = function () {
                window._retrySel = Array.prototype.filter.call(boxes, function (x) { return x.checked; })
                    .map(function (x) { return x.value; });
                var any = window._retrySel.length > 0;
                retryBtn.disabled = !any;
            };
        });
        retryBtn.disabled = !(window._retrySel && window._retrySel.length > 0);
    }

    function poll() {
        fetch(rowsUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function () {});
    }

    poll();
    setInterval(poll, 5000);
})();
</script>
@endsection
