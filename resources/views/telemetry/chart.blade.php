@extends('layouts.app')

@section('title', 'Telemetry Chart')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11">

            {{-- Page Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <span style="font-size: 1.6rem;">&#128200;</span>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">Telemetry Line Chart</h3>
                        <small class="text-muted">Visualize telemetry parameters over time</small>
                    </div>
                </div>
            </div>

            {{-- Filters Card --}}
            <div class="card border-0 rounded-3 shadow-sm mb-4 overflow-hidden">
                <div class="card-header bg-primary text-white fw-semibold py-3 d-flex align-items-center gap-2">
                    <span style="font-size: 1.1rem;">&#128269;</span> Filters
                </div>
                <div class="card-body p-4">
                    <form id="chartFilterForm" method="GET" action="{{ route('telemetry.chart') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="collector_id" class="form-label fw-medium">Collector ID (IMEI)</label>
                                <input type="text" name="collector_id" id="collector_id"
                                    class="form-control @error('collector_id') is-invalid @enderror"
                                    placeholder="e.g. 86505607001024"
                                    value="{{ old('collector_id', $collectorId) }}"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label fw-medium">From Date</label>
                                <input type="date" name="date_from" id="date_from"
                                    class="form-control"
                                    value="{{ old('date_from', $dateFrom) }}"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label fw-medium">To Date</label>
                                <input type="date" name="date_to" id="date_to"
                                    class="form-control"
                                    value="{{ old('date_to', $dateTo) }}"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="x_axis" class="form-label fw-medium">X-Axis</label>
                                <select name="x_axis" id="x_axis" class="form-select">
                                    <option value="created_at" selected>created_at (DB)</option>
                                    <option value="TIMESTAMP">TIMESTAMP (Payload)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Parameters Multi-Select --}}
                        <div class="mt-4">
                            <label class="form-label fw-medium mb-2">Select Parameters to Plot</label>
                            <div class="d-flex flex-wrap gap-2" id="paramContainer">
                                @php
                                    $allParams = [
                                        'I', 'PF', 'VN',
                                        'FT1', 'FT2', 'FT3', 'FT4', 'FT5',
                                        'IST', 'LON', 'POW', 'TON',
                                        'APOW', 'BPHI', 'BPHV',
                                        'DCI1', 'DCV1', 'FREQ', 'LKWH',
                                        'POWB', 'POWR', 'POWY',
                                        'RPHI', 'RPHV', 'RPOW',
                                        'TEMP', 'TKWH',
                                        'YPHI', 'YPHV', 'DCKW1'
                                    ];
                                @endphp
                                @foreach($allParams as $param)
                                    <label class="param-chip d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill border cursor-pointer user-select-none"
                                           style="font-size: 0.85rem;"
                                           data-param="{{ $param }}">
                                        <input type="checkbox" name="parameters[]" value="{{ $param }}" class="d-none param-check">
                                        <span class="check-icon opacity-25">&#10003;</span>
                                        <span>{{ $param }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-2 d-flex gap-2 align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllParams">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllParams">Clear All</button>
                                <small class="text-muted ms-2" id="paramCountHint">0 selected</small>
                                <div class="ms-auto d-flex align-items-center gap-3">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="normalizeToggle">
                                        <label class="form-check-label small" for="normalizeToggle">Normalize each line (0-100%)</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="loadChartBtn">
                                        <span>&#128200;</span> Load Chart
                                    </button>
                                </div>
                            </div>
                            <div id="validationAlert" class="alert alert-danger py-2 mt-2 mb-0" style="display: none;"></div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Chart Card --}}
            <div class="card border-0 rounded-3 shadow-sm mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <h5 class="fw-bold mb-0">Telemetry Overview</h5>
                            <small class="text-muted" id="chartSubtitle"></small>
                        </div>
                        <span class="badge bg-primary" id="dataCountBadge" style="display: none;">0 points</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetZoomBtn" style="display: none;">
                            &#128269; Reset Zoom
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="downloadChartBtn" style="display: none;">
                            &#128247; Save Image
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="noDataMessage" class="text-center py-5 text-muted">
                        <div style="font-size: 3.5rem; opacity: 0.4;">&#128200;</div>
                        <p class="mt-3 mb-0 fw-medium">No chart data loaded</p>
                        <p class="small text-muted">Select filters and parameters, then click Load Chart</p>
                    </div>
                    <div id="chartContainer" class="d-flex" style="display: none !important;">
                        <div class="flex-grow-1 p-3" style="min-width: 0;">
                            <canvas id="telemetryChart" height="320"></canvas>
                        </div>
                        <div id="chartLegend" class="chart-legend-sidebar border-start p-3" style="width: 200px; display: none;">
                            <small class="text-uppercase text-muted fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Metrics</small>
                            <div id="legendItems" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.param-chip {
    background: #f8f9fa;
    border-color: #dee2e6 !important;
    color: #495057;
    transition: all 0.15s ease;
    cursor: pointer;
}
.param-chip:hover {
    background: #e9ecef;
    border-color: #adb5bd !important;
}
.param-chip.active {
    background: #0d6efd;
    border-color: #0d6efd !important;
    color: #fff;
}
.param-chip.active .check-icon {
    opacity: 1 !important;
}
.param-chip .check-icon {
    font-size: 0.75rem;
}
.chart-legend-sidebar {
    background: #fafbfc;
    overflow-y: auto;
    max-height: 360px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    cursor: pointer;
    font-size: 0.8rem;
    color: #495057;
    user-select: none;
    border-bottom: 1px solid #f0f0f0;
}
.legend-item:last-child {
    border-bottom: none;
}
.legend-item:hover {
    color: #212529;
}
.legend-item input[type="checkbox"] {
    cursor: pointer;
    accent-color: #0d6efd;
}
.legend-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 2px solid transparent;
}
.legend-item.hidden-line .legend-color-dot {
    opacity: 0.3;
}
.legend-item.hidden-line .legend-label {
    opacity: 0.4;
    text-decoration: line-through;
}
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
<script>
const paramChips = document.querySelectorAll('.param-chip');
const selectAllBtn = document.getElementById('selectAllParams');
const clearAllBtn = document.getElementById('clearAllParams');
const form = document.getElementById('chartFilterForm');
const loadBtn = document.getElementById('loadChartBtn');
const noDataMsg = document.getElementById('noDataMessage');
const chartContainer = document.getElementById('chartContainer');
const dataCountBadge = document.getElementById('dataCountBadge');

let chartInstance = null;

// Toggle parameter chips via checkbox change (avoids double-toggle from label click)
document.querySelectorAll('.param-check').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('.param-chip').classList.toggle('active', this.checked);
        updateParamCount();
    });
});

function updateParamCount() {
    const count = document.querySelectorAll('.param-check:checked').length;
    document.getElementById('paramCountHint').textContent = count + ' selected';
}

// Initialize checked state from URL params
const urlParams = new URLSearchParams(window.location.search);
const selectedParams = urlParams.getAll('parameters[]');
if (selectedParams.length > 0) {
    selectedParams.forEach(param => {
        const chip = document.querySelector(`[data-param="${param}"]`);
        if (chip) {
            chip.querySelector('.param-check').checked = true;
            chip.classList.add('active');
        }
    });
    updateParamCount();
}

selectAllBtn.addEventListener('click', () => {
    paramChips.forEach(chip => {
        chip.querySelector('.param-check').checked = true;
        chip.classList.add('active');
    });
    updateParamCount();
});

clearAllBtn.addEventListener('click', () => {
    paramChips.forEach(chip => {
        chip.querySelector('.param-check').checked = false;
        chip.classList.remove('active');
    });
    updateParamCount();
});

function showValidation(msg) {
    const el = document.getElementById('validationAlert');
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// Form submit handler (AJAX)
form.addEventListener('submit', function(e) {
    e.preventDefault();

    const checked = document.querySelectorAll('.param-check:checked');
    if (checked.length === 0) {
        showValidation('Please select at least one parameter to plot.');
        return;
    }

    const collectorId = document.getElementById('collector_id').value.trim();
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const parameters = Array.from(checked).map(cb => cb.value);
    const normalize = document.getElementById('normalizeToggle').checked;
    const xAxis = document.getElementById('x_axis').value;

    loadBtn.disabled = true;
    loadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';

    fetch('{{ route("telemetry.chart.data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            collector_id: collectorId,
            date_from: dateFrom,
            date_to: dateTo,
            parameters: parameters,
            normalize: normalize,
            x_axis: xAxis
        })
    })
    .then(r => r.json())
    .then(res => {
        loadBtn.disabled = false;
        loadBtn.innerHTML = '<span>&#128200;</span> Load Chart';

        if (!res.success || !res.data) {
            showValidation(res.message || 'Failed to load chart data');
            return;
        }

        const chartData = res.data;
        if (chartData.count === 0) {
            noDataMsg.style.display = 'block';
            chartContainer.style.display = 'none';
            dataCountBadge.style.display = 'none';
            noDataMsg.innerHTML = `
                <div style="font-size: 3.5rem; opacity: 0.4;">&#128200;</div>
                <p class="mt-3 mb-0 fw-medium">No data found for the selected filters</p>
                <p class="small text-muted">Try a different date range or collector ID</p>
            `;
            return;
        }

        noDataMsg.style.display = 'none';
        chartContainer.style.display = 'flex';
        chartContainer.style.removeProperty('display');
        document.getElementById('chartLegend').style.display = 'block';
        dataCountBadge.style.display = 'inline-block';
        dataCountBadge.textContent = chartData.count + ' points';
        document.getElementById('downloadChartBtn').style.display = 'inline-block';
        document.getElementById('resetZoomBtn').style.display = 'inline-block';

        // Set subtitle
        const collectorId = document.getElementById('collector_id').value.trim();
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;
        const xAxisLabel = xAxis === 'TIMESTAMP' ? 'TIMESTAMP (payload)' : 'created_at (DB)';
        document.getElementById('chartSubtitle').textContent =
            'Collector: ' + collectorId + '  |  ' + dateFrom + ' to ' + dateTo + '  |  X-Axis: ' + xAxisLabel;

        renderChart(chartData.labels, chartData.datasets, chartData.normalized || false);
    })
    .catch(err => {
        loadBtn.disabled = false;
        loadBtn.innerHTML = '<span>&#128200;</span> Load Chart';
        showValidation('Error loading chart: ' + err.message);
    });
});

function renderChart(labels, datasets, normalized) {
    const ctx = document.getElementById('telemetryChart').getContext('2d');

    if (chartInstance) {
        chartInstance.destroy();
    }

    // Add fill and smooth tension to each dataset
    datasets.forEach(ds => {
        ds.fill = true;
        ds.tension = 0.4;
        ds.pointRadius = 0;
        ds.pointHoverRadius = 4;
    });

    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 12 },
                    bodyFont: { size: 11 },
                    padding: 10,
                    cornerRadius: 4,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (normalized && context.parsed.y !== null) {
                                label += context.parsed.y.toFixed(1) + '%';
                            } else {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                },
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                        modifierKey: null
                    },
                    zoom: {
                        wheel: { enabled: true },
                        pinch: { enabled: true },
                        mode: 'x',
                        drag: {
                            enabled: true,
                            backgroundColor: 'rgba(0,0,0,0.1)',
                            borderColor: 'rgba(0,0,0,0.3)',
                            borderWidth: 1
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 12,
                        maxRotation: 45,
                        minRotation: 30,
                        font: { size: 10 }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: normalized ? 'Normalized (%)' : 'Value',
                        font: { weight: 'bold', size: 12 }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    min: normalized ? 0 : undefined,
                    max: normalized ? 100 : undefined
                }
            }
        }
    });

    buildCustomLegend(chartInstance);
}

function buildCustomLegend(chart) {
    const container = document.getElementById('legendItems');
    container.innerHTML = '';

    chart.data.datasets.forEach((dataset, index) => {
        const color = dataset.borderColor;
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.innerHTML = `
            <input type="checkbox" checked data-index="${index}">
            <span class="legend-color-dot" style="background-color: ${color}; border-color: ${color};"></span>
            <span class="legend-label">${dataset.label}</span>
        `;

        const checkbox = item.querySelector('input[type="checkbox"]');
        checkbox.addEventListener('change', function() {
            chart.setDatasetVisibility(index, this.checked);
            chart.update();
            item.classList.toggle('hidden-line', !this.checked);
        });

        item.addEventListener('click', function(e) {
            if (e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        });

        container.appendChild(item);
    });
}

// Download chart as PNG image
document.getElementById('downloadChartBtn').addEventListener('click', function() {
    if (!chartInstance) return;
    const link = document.createElement('a');
    link.download = 'telemetry-chart-' + new Date().toISOString().slice(0, 10) + '.png';
    link.href = chartInstance.toBase64Image();
    link.click();
});

// Reset zoom
document.getElementById('resetZoomBtn').addEventListener('click', function() {
    if (chartInstance) {
        chartInstance.resetZoom();
    }
});
</script>
@endsection
