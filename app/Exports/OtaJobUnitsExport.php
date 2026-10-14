<?php

namespace App\Exports;

use App\Models\OtaJob;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OtaJobUnitsExport implements FromCollection, WithHeadings
{
    public function __construct(private OtaJob $job) {}

    public function headings(): array
    {
        return [
            'IMEI',
            'Site',
            'Note',
            'Version Before',
            'Version After',
            'State',
            'Reason',
            'Detail',
            'Attempt',
            'Version Differs',
            'Confirmed by Telemetry',
            'Unit Timestamp (IST)',
            'Sent At',
            'Last Update',
        ];
    }

    public function collection(): Collection
    {
        return $this->job->units()->orderBy('id')->get()->map(fn ($u) => [
            $u->imei,
            $u->site,
            $u->note,
            $u->version_before,
            $u->version_after,
            $u->state,
            $u->reason,
            $u->detail,
            $u->attempt,
            $u->version_differs ? 'yes' : 'no',
            $u->confirmed_by_telemetry ? 'yes' : 'no',
            $u->unit_ts,
            optional($u->sent_at)->toDateTimeString(),
            optional($u->last_update_at)->toDateTimeString(),
        ]);
    }
}
