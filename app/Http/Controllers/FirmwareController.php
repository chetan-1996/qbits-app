<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class FirmwareController extends Controller
{
    private const FIRMWARE_DIR = 'static/firmware';
    private const MAX_FILE_SIZE = 10240; // 10 MB in KB
    private const ALLOWED_EXT = 'bin';

    public function index()
    {
        $files = $this->listFirmwareFiles();

        return view('firmware.index', [
            'files' => $files,
            'baseUrl' => $this->getBaseUrl(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firmware' => ['required', 'file', 'max:' . self::MAX_FILE_SIZE],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('firmware.index')
                ->withErrors($validator)
                ->withInput();
        }

        /** @var UploadedFile $file */
        $file = $request->file('firmware');

        // 1. Strict filename sanitization
        $safeName = $this->sanitizeFileName($file->getClientOriginalName());

        if ($safeName === null) {
            Log::warning('Firmware upload rejected: invalid filename', [
                'ip' => $request->ip(),
                'original_name' => $file->getClientOriginalName(),
            ]);
            return redirect()
                ->route('firmware.index')
                ->with('error', 'Invalid filename. Only letters, numbers, hyphens, underscores, and dots allowed. Must end with .bin');
        }

        // 2. Extension check (defense in depth)
        if (strtolower($file->getClientOriginalExtension()) !== self::ALLOWED_EXT) {
            Log::warning('Firmware upload rejected: bad extension', [
                'ip' => $request->ip(),
                'filename' => $safeName,
            ]);
            return redirect()
                ->route('firmware.index')
                ->with('error', 'Only .bin files are allowed.');
        }

        // 3. Magic bytes / content validation (ensure it's actually a binary file)
        if (!$this->isValidBinary($file)) {
            Log::warning('Firmware upload rejected: content validation failed', [
                'ip' => $request->ip(),
                'filename' => $safeName,
            ]);
            return redirect()
                ->route('firmware.index')
                ->with('error', 'File content does not appear to be a valid firmware binary.');
        }

        $disk = Storage::disk('public');
        $path = self::FIRMWARE_DIR . '/' . $safeName;

        if ($disk->exists($path)) {
            Log::info('Firmware upload skipped: file already exists', ['filename' => $safeName]);
            return redirect()
                ->route('firmware.index')
                ->with('warning', "File '{$safeName}' already exists. Upload skipped.")
                ->with('downloadUrl', route('firmware.download', ['file' => $safeName]));
        }

        // 4. Store with safe filename
        $file->storeAs(self::FIRMWARE_DIR, $safeName, 'public');

        Log::info('Firmware uploaded', [
            'ip' => $request->ip(),
            'filename' => $safeName,
            'size' => $file->getSize(),
        ]);

        $downloadUrl = route('firmware.download', ['file' => $safeName]);

        return redirect()
            ->route('firmware.index')
            ->with('success', "Firmware '{$safeName}' uploaded successfully.")
            ->with('downloadUrl', $downloadUrl);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'file' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-\.]+\.bin$/'],
        ]);

        $fileName = $request->input('file');
        $path = self::FIRMWARE_DIR . '/' . $fileName;
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
            Log::info('Firmware deleted', ['ip' => $request->ip(), 'filename' => $fileName]);
            return redirect()
                ->route('firmware.index')
                ->with('success', "File '{$fileName}' deleted.");
        }

        return redirect()
            ->route('firmware.index')
            ->with('error', "File '{$fileName}' not found.");
    }

    private function listFirmwareFiles(): array
    {
        $disk = Storage::disk('public');

        if (!$disk->exists(self::FIRMWARE_DIR)) {
            return [];
        }

        $files = $disk->files(self::FIRMWARE_DIR);
        $result = [];

        foreach ($files as $file) {
            $fileName = basename($file);
            if (!$this->isValidFileName($fileName)) {
                continue; // Skip any suspicious files
            }
            $result[] = [
                'name' => $fileName,
                'size' => $this->formatBytes($disk->size($file)),
                'url' => route('firmware.download', ['file' => $fileName]),
                'updated' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                'lastModified' => $disk->lastModified($file),
            ];
        }

        // Sort by last modified descending (newest first)
        usort($result, function ($a, $b) {
            return $b['lastModified'] <=> $a['lastModified'];
        });

        // Remove the internal sort key before returning
        return array_map(function ($item) {
            unset($item['lastModified']);
            return $item;
        }, $result);
    }

    /**
     * Sanitize uploaded filename. Returns null if unsafe.
     */
    private function sanitizeFileName(string $name): ?string
    {
        // Reject null bytes
        if (str_contains($name, "\0")) {
            return null;
        }

        // Reject path traversal attempts
        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return null;
        }

        // Only allow safe characters
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.bin$/', $name)) {
            return null;
        }

        // Max length
        if (strlen($name) > 128) {
            return null;
        }

        // Reject double extensions or hidden files
        $base = basename($name);
        if (str_starts_with($base, '.') || substr_count($base, '.') > 1) {
            return null;
        }

        return $base;
    }

    private function isValidFileName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-\.]+\.bin$/', $name) === 1;
    }

    /**
     * Validate file content is safe (block scripts, executables, archives)
     * Real firmware .bin files may contain printable strings — that's OK.
     */
    private function isValidBinary(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 256);
        fclose($handle);

        if ($header === false || strlen($header) < 2) {
            return false;
        }

        // Reject common dangerous signatures
        $dangerous = [
            "<?php", "<?=", "<? ", "<?\t", "<%",
            "<!DOCTYPE", "<html", "<script", "<style", "<object", "<iframe",
            "PK\x03\x04",       // ZIP
            "PK\x05\x06",       // ZIP (empty)
            "PK\x07\x08",       // ZIP (spanned)
            "MZ",               // Windows executable
            "\x7fELF",          // Linux executable
            "\x89PNG",           // PNG (disguised image)
            "GIF87a", "GIF89a", // GIF
            "\xff\xd8\xff",     // JPEG
            "%PDF",              // PDF
            "#EXTM3U",           // M3U playlist (vector for some attacks)
        ];

        $lowerHeader = strtolower($header);
        foreach ($dangerous as $sig) {
            if (str_contains($lowerHeader, strtolower($sig))) {
                return false;
            }
        }

        return true;
    }

    private function getBaseUrl(): string
    {
        return rtrim(config('app.url', url('')), '/');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
