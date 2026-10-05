<?php

namespace App\Http\Controllers;

use App\Models\Firmware;
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
        $files = Firmware::orderByDesc('created_at')->get()->map(fn (Firmware $f) => [
            'name' => $f->filename,
            'version' => $f->version,
            'size' => $this->formatBytes($f->size),
            'sha256' => $f->sha256,
            'notes' => $f->notes,
            'is_backup' => $f->is_backup,
            'url' => $f->deviceUrl(),
            'updated' => optional($f->created_at)->toDateTimeString(),
        ])->all();

        return view('firmware.index', [
            'files' => $files,
            'baseUrl' => $this->getBaseUrl(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firmware' => ['required', 'file', 'max:' . self::MAX_FILE_SIZE],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'unique:firmwares,version'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('firmware.index')
                ->withErrors($validator)
                ->withInput();
        }

        /** @var UploadedFile $file */
        $file = $request->file('firmware');
        $version = $request->input('version');

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

        // 4. MO-45: read the version from the image header and refuse the file
        //    when it differs from the version entered.
        $imageVersion = $this->readImageVersion($file);
        if ($imageVersion === null) {
            Log::warning('Firmware upload rejected: header check failed', [
                'ip' => $request->ip(),
                'filename' => $safeName,
            ]);
            return redirect()
                ->route('firmware.index')
                ->with('error', 'Not a valid firmware image (bad magic byte or app descriptor).');
        }

        if ($imageVersion !== $version) {
            Log::warning('Firmware upload rejected: image version mismatch', [
                'ip' => $request->ip(),
                'filename' => $safeName,
                'entered' => $version,
                'image' => $imageVersion,
            ]);
            return redirect()
                ->route('firmware.index')
                ->with('error', "Version mismatch: the image reports {$imageVersion}, you entered {$version}.")
                ->withInput();
        }

        // Store under a version-prefixed name so different versions never collide.
        $storedName = $version . '_' . $safeName;
        $disk = Storage::disk('public');
        $path = self::FIRMWARE_DIR . '/' . $storedName;

        if ($disk->exists($path) || Firmware::where('filename', $storedName)->exists()) {
            Log::info('Firmware upload skipped: file already exists', ['filename' => $storedName]);
            return redirect()
                ->route('firmware.index')
                ->with('warning', "File '{$storedName}' already exists. Upload skipped.");
        }

        // Hash/size must be read before storeAs() moves the temp file.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();

        $file->storeAs(self::FIRMWARE_DIR, $storedName, 'public');

        Firmware::create([
            'version' => $version,
            'filename' => $storedName,
            'path' => $path,
            'size' => $size,
            'sha256' => $sha256,
            'notes' => $request->input('notes'),
            'is_backup' => $version === config('ota.backup_version'),
            'uploaded_by_role' => 'admin',
        ]);

        Log::info('Firmware uploaded', [
            'ip' => $request->ip(),
            'filename' => $storedName,
            'version' => $version,
            'size' => $file->getSize(),
        ]);

        $downloadUrl = rtrim(config('ota.firmware_base_url'), '/') . '/firmware/download/' . $storedName;

        return redirect()
            ->route('firmware.index')
            ->with('success', "Firmware '{$storedName}' (v{$version}) uploaded successfully.")
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

        Firmware::where('filename', $fileName)->delete();

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

    /**
     * MO-45: validate the image header and read the version from the image.
     *  - byte 0 must be 0xE9
     *  - the 32-bit little-endian word at offset 32 must be 0xABCD5432
     *  - bytes 48-79 hold the version as a NUL-terminated string
     * Returns the image version, or null when the checks fail.
     */
    private function readImageVersion(UploadedFile $file): ?string
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return null;
        }

        $header = fread($handle, 80);
        fclose($handle);

        if ($header === false || strlen($header) < 80) {
            return null;
        }

        if (ord($header[0]) !== 0xE9) {
            return null;
        }

        $magic = unpack('V', substr($header, 32, 4))[1];
        if ($magic !== 0xABCD5432) {
            return null;
        }

        $rawVersion = substr($header, 48, 32);
        $nul = strpos($rawVersion, "\0");
        if ($nul !== false) {
            $rawVersion = substr($rawVersion, 0, $nul);
        }

        $version = trim($rawVersion);

        return $version !== '' ? $version : null;
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
