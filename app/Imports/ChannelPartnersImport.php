<?php

namespace App\Imports;

use App\Models\ChannelPartner;
use App\Models\City;
use App\Models\State;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class ChannelPartnersImport implements ToModel, WithHeadingRow, SkipsOnError, SkipsOnFailure
{
    public int $processed = 0;
    public int $skipped = 0;
    public int $inserted = 0;
    public int $duplicates = 0;
    public int $emptyMobile = 0;
    public array $importErrors = [];
    public array $importFailures = [];

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->importFailures[] = [
                'row'      => $failure->row(),
                'attribute'=> $failure->attribute(),
                'errors'   => $failure->errors(),
                'values'   => $failure->values(),
            ];
            Log::error('Import row failed', [
                'row'      => $failure->row(),
                'attribute'=> $failure->attribute(),
                'errors'   => $failure->errors(),
                'values'   => $failure->values(),
            ]);
        }
    }

    public function onError(\Throwable $e)
    {
        $this->importErrors[] = $e->getMessage();
        Log::error('Import error', ['message' => $e->getMessage()]);
    }

    public function errors(): array
    {
        return $this->importErrors;
    }

    public function failures(): array
    {
        return $this->importFailures;
    }

    public function model(array $row): ?ChannelPartner
    {
        $this->processed++;

        Log::info('Raw row data', ['row' => $row]);

        $mobile = $this->normalizePhone($row['mobile'] ?? '');

        $this->inserted++;

        $stateValue = $row['state'] ?? '';
        $cityName = $row['city'] ?? '';
        $latlong = $row['latlong'] ?? '';

        $stateId = $this->getStateId($stateValue);
        $cityId = $this->getOrCreateCityId($cityName, $stateId);
        list($lat, $lng) = $this->parseLatLong($latlong);

        $data = [
            'photo'         => $row['photo'] ?? '',
            'name'          => $row['name'] ?? '',
            'company_name'  => $row['company'] ?? ($row['company_name'] ?? ''),
            'designation'   => $row['designation'] ?? '',
            'mobile'        => $mobile,
            'whatsapp_no'   => $this->normalizePhone($row['whatsapp'] ?? ($row['whatsapp_no'] ?? $mobile)),
            'address'       => $row['address'] ?? '',
            'state'         => $stateId,
            'city'          => $cityId,
            'latitude'      => $lat,
            'longitude'     => $lng,
        ];

        try {
            return new ChannelPartner($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                $this->duplicates++;
                Log::warning('Import skipped: duplicate DB constraint', [
                    'mobile' => $mobile,
                    'city'   => $cityName,
                ]);
                return null;
            }
            throw $e;
        }
    }

    private function getStateId($stateValue): int
    {
        if (is_numeric($stateValue)) {
            return (int) $stateValue;
        }
        $state = State::where('name', trim($stateValue))->first();
        return $state ? $state->id : 0;
    }

    private function getOrCreateCityId($cityName, int $stateId): int
    {
        $cityName = trim((string) $cityName);
        if (empty($cityName)) {
            return 0;
        }
        $city = City::where('name', $cityName)->first();
        if ($city) {
            return $city->id;
        }
        $newCity = City::create([
            'name'       => $cityName,
            'state_id'   => $stateId,
            'status'     => 1,
            'company_id' => 0,
        ]);
        Log::info('Created new city during import', ['city' => $cityName, 'id' => $newCity->id]);
        return $newCity->id;
    }

    private function parseLatLong($value): array
    {
        if (empty($value)) {
            return [0.0, 0.0];
        }
        if (is_numeric($value)) {
            return [(float) $value, 0.0];
        }
        $parts = preg_split('/[|,\s]+/', (string) $value, 2);
        if (count($parts) === 2) {
            $lat = $this->parseDecimal($parts[0]);
            $lng = $this->parseDecimal($parts[1]);
            return [$lat, $lng];
        }
        $cleaned = $this->parseDecimal($value);
        return [$cleaned, 0.0];
    }

    private function normalizePhone($phone): string
    {
        $phone = (string) $phone;
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return substr($phone, 0, 20);
    }

    private function parseDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }
}
