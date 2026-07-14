<?php

namespace App\Imports;

use App\Models\Dongle;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DonglesImport implements ToCollection, WithCalculatedFormulas
{
    public int $processed = 0;
    public int $inserted = 0;
    public int $duplicates = 0;
    public int $skipped = 0;
    public array $importErrors = [];
    public array $debugRows = [];

    public function collection(Collection $rows)
    {
        $batch = [];
        foreach ($rows as $idx => $row) {
            $this->processed++;
            $rowArray = $row->toArray();

            if ($this->processed <= 3) {
                $this->debugRows[] = [
                    'row_num'    => $this->processed,
                    'raw'        => $rowArray,
                    'col0'       => $this->normalize($rowArray[0] ?? ''),
                    'col1'       => $this->normalize($rowArray[1] ?? ''),
                    'col2'       => $this->normalize($rowArray[2] ?? ''),
                    'col3'       => $this->normalize($rowArray[3] ?? ''),
                ];
            }

            $dongleId = $this->normalize($rowArray[0] ?? '');
            $imei     = $this->normalize($rowArray[1] ?? '');
            $imsi     = $this->normalize($rowArray[2] ?? '');
            $simNum   = $this->normalize($rowArray[3] ?? '');

            if (empty($dongleId) || empty($imei) || empty($imsi) || empty($simNum)) {
                $this->skipped++;
                continue;
            }

            // Skip header rows: IMEI must be numeric only
            if (!preg_match('/^\d+$/', $imei)) {
                $this->skipped++;
                continue;
            }

            $batch[] = [
                'dongle_id' => $dongleId,
                'imei'      => $imei,
                'imsi'      => $imsi,
                'sim_num'   => $simNum,
                'status'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($batch)) {
            return;
        }

        foreach ($batch as $data) {
            try {
                Dongle::create($data);
                $this->inserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                    $this->duplicates++;
                } else {
                    $this->importErrors[] = $e->getMessage();
                }
            }
        }
    }

    private function normalize($value): string
    {
        $value = (string) $value;
        return trim($value);
    }

    public function errors(): array
    {
        return $this->importErrors;
    }
}
