@extends('layouts.app')

@section('title', 'Dongle List')

@section('content')
<div class="container">
    <h2 class="mb-4">Dongle List</h2>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Dongle ID</th>
                            <th>IMEI</th>
                            <th>IMSI</th>
                            <th>SIM Number</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dongles as $dongle)
                            <tr>
                                <td>{{ $dongles->firstItem() + $loop->index }}</td>
                                <td>{{ $dongle->dongle_id }}</td>
                                <td>{{ $dongle->imei }}</td>
                                <td>{{ $dongle->imsi }}</td>
                                <td>{{ $dongle->sim_num }}</td>
                                <td>
                                    @if ($dongle->status == 1)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $dongle->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No dongles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $dongles->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
