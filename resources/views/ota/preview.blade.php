@extends('layouts.app')

@section('title', 'Configure OTA Job ' . $job->otaid)

@section('content')
@php
    $validRows = $rows->filter(fn ($r) => $r['unit']->state === 'QUEUED');
    $invalidRows = $rows->filter(fn ($r) => $r['unit']->state === 'INVALID');
    $neverSeen = $validRows->filter(fn ($r) => $r['never_seen'])->count();
    $offline = $validRows->filter(fn ($r) => $r['offline'])->count();
@endphp
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Configure Job <span class="font-monospace">{{ $job->otaid }}</span></h3>
        <span class="badge bg-light text-dark border fs-6">DRAFT</span>
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

    {{-- Counts (MO-06) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-primary">{{ $validRows->count() }}</div>
                    <div class="text-muted small">To send</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="fs-3 fw-bold" id="uptodateCount">0</div>
                    <div class="text-muted small">Already on target (skipped)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-danger">{{ $invalidRows->count() }}</div>
                    <div class="text-muted small">Invalid (never sent)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 text-center">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-warning">{{ $neverSeen + $offline }}</div>
                    <div class="text-muted small">Never seen / offline &gt;24h</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Invalid rows (MO-03) --}}
    @if ($invalidRows->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-danger text-white fw-semibold py-2">
                Invalid rows ({{ $invalidRows->count() }}) — these will never be sent
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-4">IMEI</th><th>Site</th><th>Note</th><th>Reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invalidRows as $r)
                            <tr>
                                <td class="ps-4 font-monospace">{{ $r['unit']->imei }}</td>
                                <td>{{ $r['unit']->site }}</td>
                                <td>{{ $r['unit']->note }}</td>
                                <td class="text-danger">{{ $r['unit']->detail }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Valid units (MO-04) --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-semibold py-2">
            Units ({{ $validRows->count() }})
        </div>
        <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4">IMEI</th>
                        <th>Site</th>
                        <th>Note</th>
                        <th>Current Version</th>
                        <th>Last Seen</th>
                        <th>Flags</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($validRows as $r)
                        <tr data-fwver="{{ $r['fw_ver'] }}">
                            <td class="ps-4 font-monospace">{{ $r['unit']->imei }}</td>
                            <td>{{ $r['unit']->site }}</td>
                            <td>{{ $r['unit']->note }}</td>
                            <td class="font-monospace">{{ $r['fw_ver'] ?? '—' }}</td>
                            <td class="small text-muted">{{ $r['last_seen'] ? $r['last_seen']->toDateTimeString() : '—' }}</td>
                            <td>
                                @if ($r['never_seen'])
                                    <span class="badge bg-danger">NEVER SEEN</span>
                                @elseif ($r['offline'])
                                    <span class="badge bg-warning text-dark">OFFLINE &gt;24h</span>
                                @endif
                                @if (!$r['known'])
                                    <span class="badge bg-secondary">NOT IN REGISTRY</span>
                                @endif
                                <span class="badge bg-info text-dark d-none uptodate-flag">UP TO DATE</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Define & send (MO-10..15, MO-44) --}}
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-primary text-white fw-semibold py-3">
            2. Choose Firmware &amp; Send
        </div>
        <div class="card-body p-4">
            @if ($firmwares->isEmpty())
                <div class="alert alert-warning mb-0">
                    No firmware in the registry. An administrator must upload one under
                    <a href="{{ route('firmware.index') }}">Firmware</a> first.
                </div>
            @else
            <form method="POST" action="{{ route('ota.jobs.send', $job) }}" id="sendForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Target firmware (MO-10)</label>
                        <select name="firmware_id" id="firmwareSelect" class="form-select" required>
                            <option value="">— select —</option>
                            @foreach ($firmwares as $fw)
                                <option value="{{ $fw->id }}"
                                        data-version="{{ $fw->version }}"
                                        data-backup="{{ $fw->is_backup ? 1 : 0 }}"
                                        @if ($fw->is_backup && !$isAdmin) disabled @endif>
                                    {{ $fw->version }} — {{ $fw->filename }}{{ $fw->is_backup ? ' (backup, admin only)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">OTA ID (MO-11)</label>
                        <input type="text" name="otaid" class="form-control font-monospace"
                               value="{{ $job->otaid }}" pattern="[A-Za-z0-9_\-]{1,32}" maxlength="32" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Pacing (MO-15)</label>
                        <div class="input-group">
                            <input type="number" name="send_gap_seconds" class="form-control" value="{{ $defaultGap }}" min="0" max="3600" title="Gap between sends (s)">
                            <span class="input-group-text">s gap</span>
                            <input type="number" name="max_concurrent" class="form-control" value="{{ $defaultConcurrent }}" min="1" max="500" title="Max concurrent downloads">
                            <span class="input-group-text">at once</span>
                        </div>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="include_uptodate" id="includeUptodate" value="1">
                    <label class="form-check-label" for="includeUptodate">
                        Include units already on the target version (MO-05)
                    </label>
                </div>

                @if ($isAdmin)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="force" id="forceCheck" value="1">
                        <label class="form-check-label text-danger" for="forceCheck">
                            <strong>force</strong> — allows downgrade and reinstalls on units already on
                            this version (MO-12)
                        </label>
                    </div>
                    <div class="mt-2 d-none" id="forceConfirmWrap">
                        <input type="text" name="force_confirm" class="form-control w-auto d-inline-block"
                               placeholder="Type FORCE to confirm" autocomplete="off">
                    </div>
                @endif

                {{-- Confirmation summary (MO-44) --}}
                <div class="alert alert-info mt-4 mb-3">
                    <strong>Confirm:</strong> send version
                    <span class="font-monospace fw-bold" id="confirmVersion">—</span> to
                    <span class="fw-bold" id="confirmCount">{{ $validRows->count() }}</span> unit(s),
                    force <span class="fw-bold" id="confirmForce">off</span>.
                </div>

                <button type="submit" class="btn btn-success btn-lg px-5" id="sendBtn" disabled>
                    Send OTA Commands
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var select = document.getElementById('firmwareSelect');
    if (!select) return;

    var forceCheck = document.getElementById('forceCheck');
    var forceWrap = document.getElementById('forceConfirmWrap');
    var includeUp = document.getElementById('includeUptodate');
    var sendBtn = document.getElementById('sendBtn');
    var totalValid = {{ $validRows->count() }};

    function refresh() {
        var opt = select.options[select.selectedIndex];
        var ver = opt ? (opt.getAttribute('data-version') || '') : '';
        document.getElementById('confirmVersion').textContent = ver || '—';

        var upToDate = 0;
        document.querySelectorAll('tr[data-fwver]').forEach(function (tr) {
            var match = ver !== '' && tr.getAttribute('data-fwver') === ver;
            tr.querySelector('.uptodate-flag').classList.toggle('d-none', !match);
            if (match) upToDate++;
        });
        document.getElementById('uptodateCount').textContent = upToDate;

        var sendCount = includeUp.checked ? totalValid : totalValid - upToDate;
        document.getElementById('confirmCount').textContent = sendCount;
        document.getElementById('confirmForce').textContent =
            (forceCheck && forceCheck.checked) ? 'ON (downgrade allowed)' : 'off';

        if (forceWrap) forceWrap.classList.toggle('d-none', !(forceCheck && forceCheck.checked));
        sendBtn.disabled = (ver === '' || sendCount <= 0);
    }

    select.addEventListener('change', refresh);
    includeUp.addEventListener('change', refresh);
    if (forceCheck) forceCheck.addEventListener('change', refresh);
    refresh();
})();
</script>
@endsection
