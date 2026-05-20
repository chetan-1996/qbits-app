@if(is_array($data) && count($data) > 0)
<table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
    <tbody>
        @foreach($data as $key => $value)
            <tr>
                <td class="fw-semibold text-nowrap bg-light" style="width: 35%; vertical-align: top;">{{ $key }}</td>
                <td style="vertical-align: top;">
                    @if(is_array($value) && count($value) > 0)
                        @include('telemetry._payload_table', ['data' => $value])
                    @elseif(is_array($value))
                        <span class="text-muted fst-italic">[ ]</span>
                    @elseif(is_bool($value))
                        @if($value)
                            <span class="badge bg-success">true</span>
                        @else
                            <span class="badge bg-danger">false</span>
                        @endif
                    @elseif(is_null($value))
                        <span class="text-muted fst-italic">null</span>
                    @else
                        {{ $value }}
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@else
    <span class="text-muted fst-italic">empty</span>
@endif
