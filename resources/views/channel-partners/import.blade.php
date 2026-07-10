<!DOCTYPE html>
<html>
<head>
    <title>Import Channel Partners</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h3>Import Channel Partners from Excel</h3>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form action="/channel-partners/import" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Excel File (.xlsx / .xls / .csv)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </form>

        <hr>
        <h5>Expected Columns</h5>
        <table class="table table-sm table-bordered">
            <thead>
                <tr><th>Column</th><th>Field</th><th>Required</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <tr><td>A</td><td>photo</td><td>No</td><td></td></tr>
                <tr><td>B</td><td>name</td><td><b>Yes</b></td><td></td></tr>
                <tr><td>C</td><td>company_name</td><td>No</td><td></td></tr>
                <tr><td>D</td><td>designation</td><td>No</td><td></td></tr>
                <tr><td>E</td><td>mobile</td><td><b>Yes (Unique)</b></td><td></td></tr>
                <tr><td>F</td><td>whatsapp_no</td><td>No</td><td></td></tr>
                <tr><td>G</td><td>state</td><td>No</td><td>State ID or name</td></tr>
                <tr><td>H</td><td>city</td><td>No</td><td>Auto-created if not found</td></tr>
                <tr><td>I</td><td>latlong</td><td>No</td><td>e.g. <code>30.5554,79.5636</code> or <code>30.5554|79.5636</code></td></tr>
                <tr><td>J</td><td>address</td><td>No</td><td></td></tr>
            </tbody>
        </table>
        <p class="text-muted small">If city doesn't exist in the database, it will be automatically created and linked to the state.</p>
    </div>
</body>
</html>
