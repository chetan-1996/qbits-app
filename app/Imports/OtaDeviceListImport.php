<?php

namespace App\Imports;

use App\Support\Imei;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet of the device list (MO-01). The header row names the
 * columns in any order: IMEI is required, Site and Note optional. maatwebsite
 * slugs the headers, so 'IMEI' -> 'imei', 'Site' -> 'site', 'Note' -> 'note'.
 *
 * Every item: ['imei' => normalized, 'site' => ?, 'note' => ?, 'error' => ?]
 * Invalid rows keep their error reason and are never sent (MO-03).
 */
class OtaDeviceListImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public array $items = [];
    public ?string $fatalError = null;

    public function collection(Collection $rows)
    {
        $seen = [];

        foreach ($rows as $idx => $row) {
            $arr = $row->toArray();

            if (!array_key_exists('imei', $arr)) {
                $this->fatalError = 'IMEI column not found in the first row. Use the template headers: IMEI, Site, Note.';
                return;
            }

            $imei = Imei::normalize($arr['imei'] ?? null);
            $site = isset($arr['site']) ? trim((string) $arr['site']) : null;
            $note = isset($arr['note']) ? trim((string) $arr['note']) : null;

            // Skip fully blank rows
            if ($imei === '' && ($site === null || $site === '') && ($note === null || $note === '')) {
                continue;
            }

            $item = [
                'imei' => $imei,
                'site' => $site !== '' ? $site : null,
                'note' => $note !== '' ? $note : null,
                'error' => null,
                'row' => $idx + 2, // +1 for heading row, +1 for 1-based sheet numbering
            ];

            if ($imei === '') {
                $item['error'] = 'IMEI missing';
            } elseif (isset($seen[$imei])) {
                $item['error'] = 'Duplicate of row ' . $seen[$imei];
            } elseif (!preg_match('/^\d{15}$/', $imei)) {
                $item['error'] = 'IMEI must be exactly 15 digits';
            } elseif (!Imei::luhnValid($imei)) {
                $item['error'] = 'Invalid IMEI (Luhn check digit failed)';
            }

            if ($item['error'] === null) {
                $seen[$imei] = $item['row'];
            }

            $this->items[] = $item;
        }
    }
}
