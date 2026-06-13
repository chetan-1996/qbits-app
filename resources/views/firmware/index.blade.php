@extends('layouts.app')

@section('title', 'Firmware Management')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            {{-- Page Header --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <span style="font-size: 1.6rem;">&#128190;</span>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">Firmware Management</h3>
                        <small class="text-muted">Upload and manage device firmware binaries</small>
                    </div>
                </div>
                <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill">{{ count($files) }} file{{ count($files) !== 1 ? 's' : '' }}</span>
            </div>

            {{-- Alerts --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4">
                    <div class="d-flex align-items-center">
                        <span class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">&#10003;</span>
                        <div><strong>Success!</strong> {{ session('success') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4">
                    <div class="d-flex align-items-center">
                        <span class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">&#9888;</span>
                        <div><strong>Warning:</strong> {{ session('warning') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4">
                    <div class="d-flex align-items-center">
                        <span class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">&#10007;</span>
                        <div><strong>Error:</strong> {{ session('error') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Upload Form --}}
            <div class="card border-0 rounded-3 shadow-sm mb-4 overflow-hidden">
                <div class="card-header bg-primary text-white fw-semibold py-3 d-flex align-items-center gap-2">
                    <span style="font-size: 1.1rem;">&#8593;</span> Upload New Firmware
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('firmware.store') }}" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <div class="upload-zone p-4 rounded-3 border-2 border-dashed text-center mb-3" id="dropZone">
                            <div class="text-muted mb-2" style="font-size: 2.5rem;">&#128230;</div>
                            <p class="fw-medium text-dark mb-1">Drag & drop your .bin file here</p>
                            <p class="text-muted small mb-3">or click to browse</p>
                            <input type="file" class="form-control d-none" id="firmware" name="firmware" accept=".bin" required>
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('firmware').click()">
                                Choose File
                            </button>
                            <div id="fileNameDisplay" class="mt-2 fw-medium text-primary"></div>
                        </div>
                        @error('firmware')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="form-text text-muted mb-0">
                                <span class="badge bg-light text-dark border me-1">Max 10 MB</span>
                                <span class="badge bg-light text-dark border">.bin only</span>
                            </div>
                            <button type="submit" class="btn btn-primary px-4" id="uploadBtn" disabled>
                                Upload Firmware
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Uploaded Files List --}}
            <div class="card border-0 rounded-3 shadow-sm overflow-hidden">
                <div class="card-header bg-dark text-white fw-semibold py-3">
                    Uploaded Firmware Files
                </div>
                <div class="card-body p-0">
                    @if (count($files) === 0)
                        <div class="text-center py-5 text-muted">
                            <div style="font-size: 3.5rem; opacity: 0.5;">&#128194;</div>
                            <p class="mt-3 mb-0 fw-medium">No firmware files uploaded yet</p>
                            <p class="small text-muted">Upload a .bin file to get started</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="min-width: 240px;">File Name</th>
                                        <th style="width: 110px;">Size</th>
                                        <th style="width: 190px;">Uploaded</th>
                                        <th class="pe-4 text-end" style="width: 300px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($files as $file)
                                        <tr style="height: 68px;">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                                        <span style="font-size: 1.1rem;">&#128190;</span>
                                                    </div>
                                                    <span class="fw-medium">{{ $file['name'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary bg-opacity-10 text-dark border-0 fw-normal" style="font-size: 0.85rem;">{{ $file['size'] }}</span>
                                            </td>
                                            <td class="text-muted">{{ $file['updated'] }}</td>
                                            <td class="pe-4 text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <a href="{{ $file['url'] }}" class="btn btn-success btn-sm" target="_blank">
                                                        <span>&#9660;</span> Download
                                                    </a>
                                                    <button class="btn btn-outline-secondary btn-sm" type="button"
                                                            onclick="copyToClipboard('url-{{ $loop->index }}', this)">
                                                        <span class="copy-label">Copy URL</span>
                                                    </button>
                                                    <input type="hidden" id="url-{{ $loop->index }}" value="{{ $file['url'] }}">
                                                    <form method="POST" action="{{ route('firmware.destroy') }}"
                                                          onsubmit="return confirm('Delete {{ $file['name'] }}? This cannot be undone.')"
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="file" value="{{ $file['name'] }}">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                                            <span>&#128465;</span> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.font-monospace { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.82rem; }
.copy-label { min-width: 36px; display: inline-block; text-align: center; }
.upload-zone {
    background: #f8f9fa;
    border-color: #dee2e6;
    border-style: dashed;
    cursor: pointer;
    transition: all 0.2s ease;
}
.upload-zone:hover {
    background: #e9ecef;
    border-color: #0d6efd;
}
.upload-zone.dragover {
    background: #e7f1ff;
    border-color: #0d6efd;
    border-style: solid;
}
.table tbody tr { transition: background-color 0.15s ease; }
.table tbody tr:hover { background-color: #f8f9fa !important; }
</style>
@endsection

@section('scripts')
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('firmware');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const uploadBtn = document.getElementById('uploadBtn');

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
        uploadBtn.disabled = false;
    } else {
        fileNameDisplay.textContent = '';
        uploadBtn.disabled = true;
    }
});

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
});

dropZone.addEventListener('drop', function(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length > 0) {
        fileInput.files = files;
        fileNameDisplay.textContent = 'Selected: ' + files[0].name;
        uploadBtn.disabled = false;
    }
}, false);

function copyToClipboard(elementId, btn) {
    const input = document.getElementById(elementId);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const label = btn.querySelector('.copy-label') || btn;
        const original = label.innerText;
        label.innerText = 'Copied!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            label.innerText = original;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 1500);
    }).catch(() => {
        label.innerText = 'Failed';
    });
}
</script>
@endsection
