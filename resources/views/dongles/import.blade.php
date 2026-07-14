@extends('layouts.app')

@section('title', 'Import Dongles')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Import Dongles (Excel)</h4>
                </div>
                <div class="card-body">
                    <form id="importForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="file" class="form-label">Excel File (.xlsx, .xls, .csv)</label>
                            <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <button type="submit" class="btn btn-success">Import</button>
                    </form>

                    <div id="result" class="mt-4 d-none">
                        <div class="alert" role="alert" id="alertBox"></div>
                        <table class="table table-sm table-bordered">
                            <tr><th>Processed</th><td id="resProcessed"></td></tr>
                            <tr><th>Inserted</th><td id="resInserted"></td></tr>
                            <tr><th>Duplicates</th><td id="resDuplicates"></td></tr>
                            <tr><th>Skipped</th><td id="resSkipped"></td></tr>
                            <tr><th>Errors</th><td id="resErrors"></td></tr>
                        </table>
                        <div id="debugSection" class="d-none">
                            <h6 class="mt-3">First 3 Rows (Debug)</h6>
                            <pre id="debugRows" class="bg-light p-2 small"></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Expected Columns</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Dongle ID</strong> — unique identifier (e.g. A000200051)</li>
                        <li class="list-group-item"><strong>IMEI</strong> — unique IMEI number</li>
                        <li class="list-group-item"><strong>IMSI</strong> — unique IMSI number</li>
                        <li class="list-group-item"><strong>SIM num</strong> — unique SIM number</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const resBox = document.getElementById('result');
    const alertBox = document.getElementById('alertBox');
    resBox.classList.remove('d-none');
    alertBox.className = 'alert alert-info';
    alertBox.textContent = 'Importing...';

    try {
        const response = await fetch('{{ route("dongles.import") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Server returned non-JSON response:', text.substring(0, 500));
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = '<strong>HTTP ' + response.status + '</strong> — Server returned HTML instead of JSON. Check browser console or Laravel logs.';
            return;
        }

        document.getElementById('resProcessed').textContent = data.processed ?? 0;
        document.getElementById('resInserted').textContent = data.inserted ?? 0;
        document.getElementById('resDuplicates').textContent = data.duplicates ?? 0;
        document.getElementById('resSkipped').textContent = data.skipped ?? 0;
        document.getElementById('resErrors').textContent = data.errors ?? 0;

        if (data.debug_rows && data.debug_rows.length > 0) {
            document.getElementById('debugSection').classList.remove('d-none');
            document.getElementById('debugRows').textContent = JSON.stringify(data.debug_rows, null, 2);
        } else {
            document.getElementById('debugSection').classList.add('d-none');
        }

        if (data.status) {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = data.message;
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = data.message;
        }
    } catch (err) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'Request failed: ' + err.message;
    }
});
</script>
@endsection
