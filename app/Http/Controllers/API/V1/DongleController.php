<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\BaseController;
use App\Imports\DonglesImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class DongleController extends BaseController
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');

        $import = new DonglesImport();

        try {
            Excel::import($import, $file);
        } catch (\Throwable $e) {
            Log::error('Dongle import exception', ['message' => $e->getMessage()]);
            return response()->json([
                'status'  => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'     => true,
            'message'    => 'Import completed',
            'processed'  => $import->processed,
            'inserted'   => $import->inserted,
            'duplicates' => $import->duplicates,
            'skipped'    => $import->skipped,
            'errors'     => count($import->errors()),
            'debug_rows' => $import->debugRows,
        ]);
    }
}
